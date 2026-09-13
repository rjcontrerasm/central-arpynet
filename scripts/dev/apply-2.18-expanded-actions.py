#!/usr/bin/env python3
from pathlib import Path

controller = Path('app/Http/Controllers/AgentProposalController.php')
view = Path('resources/views/agent-proposals.blade.php')

controller_text = controller.read_text()
start_marker = '    public function prepare(\n'
end_marker = '    public function approve(\n'

start = controller_text.find(start_marker)
end = controller_text.find(end_marker, start)

if start < 0 or end < 0:
    raise SystemExit('ERROR: no se encontró el bloque prepare()/approve() esperado')

new_prepare = r'''    public function prepare(
        Request $request,
        CentralAgentGateway $gateway,
    ): RedirectResponse {
        $validated = $request->validate([
            'subject_type' => [
                'required',
                Rule::in([
                    'task',
                    'project',
                    'service_order',
                ]),
            ],
            'subject_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'action' => [
                'required',
                Rule::in([
                    'complete',
                    'start',
                    'today',
                    'tomorrow',
                    'next_week',
                    'project.status.set',
                    'project.next_action.set',
                    'project.blockers.clear',
                    'project.task.create',
                    'service_order.stage.set',
                    'service_order.next_action.set',
                ]),
            ],
            'next_action' => [
                'nullable',
                'string',
                'max:255',
            ],
            'next_action_at' => [
                'nullable',
                'date',
            ],
            'project_status' => [
                'nullable',
                'required_if:action,project.status.set',
                Rule::in(array_keys(Project::statusOptions())),
            ],
            'project_task_title' => [
                'nullable',
                'required_if:action,project.task.create',
                'string',
                'max:255',
            ],
            'project_task_urgency' => [
                'nullable',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'critical',
                ]),
            ],
            'project_task_due_date' => [
                'nullable',
                'date',
            ],
            'service_stage' => [
                'nullable',
                'required_if:action,service_order.stage.set',
                Rule::in(array_keys(ServiceOrder::stageOptions())),
            ],
        ]);

        $allowedByType = [
            'task' => [
                'complete',
                'start',
                'today',
                'tomorrow',
                'next_week',
            ],
            'project' => [
                'project.status.set',
                'project.next_action.set',
                'project.blockers.clear',
                'project.task.create',
            ],
            'service_order' => [
                'service_order.stage.set',
                'service_order.next_action.set',
            ],
        ];

        if (
            ! in_array(
                $validated['action'],
                $allowedByType[
                    $validated['subject_type']
                ],
                true,
            )
        ) {
            throw \Illuminate\Validation\ValidationException::
                withMessages([
                    'action' =>
                        'La acción propuesta no corresponde al tipo de entidad.',
                ]);
        }

        $result = DB::transaction(
            function () use (
                $request,
                $gateway,
                $validated,
            ): array {
                if (
                    $validated['subject_type']
                    === 'task'
                ) {
                    $task = Task::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            (int) $validated['subject_id'],
                        );

                    $this->authorizeOrganization(
                        $request,
                        (int) $task->organization_id,
                    );

                    if (
                        in_array(
                            $task->status,
                            ['completed', 'cancelled'],
                            true,
                        )
                        || (
                            $validated['action'] === 'start'
                            && $task->status === 'in_progress'
                        )
                    ) {
                        return [
                            'stale' => true,
                            'scope' => (int) $task->organization_id,
                            'stale_message' =>
                                'La tarea cambió desde la lectura de Jarvis y esa preparación ya no es pertinente. Recarga Jarvis antes de continuar.',
                        ];
                    }

                    $proposal = $gateway->proposeTaskAction(
                        $request->user(),
                        $task,
                        $validated['action'],
                        'Propuesta de tarea preparada manualmente desde Lectura Jarvis. La tarea permanece sin cambios hasta aprobación y segunda confirmación.',
                    );

                    return [
                        'stale' => false,
                        'scope' => (int) $task->organization_id,
                        'proposal' => $proposal,
                        'created' => $proposal->wasRecentlyCreated,
                    ];
                }

                if (
                    $validated['subject_type']
                    === 'project'
                ) {
                    $project = Project::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            (int) $validated['subject_id'],
                        );

                    $this->authorizeOrganization(
                        $request,
                        (int) $project->organization_id,
                    );

                    $action = $validated['action'];
                    $payload = [];
                    $reason =
                        'Propuesta de proyecto preparada manualmente desde Lectura Jarvis. El proyecto permanece sin cambios hasta aprobación y segunda confirmación.';

                    if ($action === 'project.next_action.set') {
                        if (filled($project->next_action)) {
                            return [
                                'stale' => true,
                                'scope' => (int) $project->organization_id,
                                'stale_message' =>
                                    'La sugerencia ya no está vigente porque el proyecto ya tiene una siguiente acción. Recarga Jarvis antes de preparar otra propuesta.',
                            ];
                        }

                        $payload = [
                            'next_action' => trim(
                                (string) ($validated['next_action'] ?? ''),
                            ),
                        ];
                    } elseif ($action === 'project.status.set') {
                        $targetStatus = (string) $validated['project_status'];

                        if ($project->status === $targetStatus) {
                            return [
                                'stale' => true,
                                'scope' => (int) $project->organization_id,
                                'stale_message' =>
                                    'El proyecto ya se encuentra en el estado seleccionado. No se creó una propuesta sin cambios.',
                            ];
                        }

                        $payload = [
                            'status' => $targetStatus,
                        ];
                    } elseif ($action === 'project.blockers.clear') {
                        if (blank($project->blockers)) {
                            return [
                                'stale' => true,
                                'scope' => (int) $project->organization_id,
                                'stale_message' =>
                                    'El proyecto ya no tiene bloqueos registrados. No se creó una propuesta sin cambios.',
                            ];
                        }
                    } elseif ($action === 'project.task.create') {
                        if (
                            in_array(
                                $project->status,
                                ['completed', 'cancelled'],
                                true,
                            )
                        ) {
                            return [
                                'stale' => true,
                                'scope' => (int) $project->organization_id,
                                'stale_message' =>
                                    'El proyecto ya no admite nuevas tareas porque está completado o cancelado.',
                            ];
                        }

                        $payload = [
                            'title' => trim(
                                (string) $validated['project_task_title'],
                            ),
                            'urgency' =>
                                $validated['project_task_urgency']
                                ?? 'normal',
                            'due_date' =>
                                $validated['project_task_due_date']
                                ?? null,
                        ];
                    }

                    $proposal = $gateway->proposeProjectAction(
                        $request->user(),
                        $project,
                        $action,
                        $payload,
                        $reason,
                    );

                    return [
                        'stale' => false,
                        'scope' => (int) $project->organization_id,
                        'proposal' => $proposal,
                        'created' => $proposal->wasRecentlyCreated,
                    ];
                }

                $order = ServiceOrder::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        (int) $validated['subject_id'],
                    );

                $this->authorizeOrganization(
                    $request,
                    (int) $order->organization_id,
                );

                $action = $validated['action'];
                $payload = [];

                if ($action === 'service_order.next_action.set') {
                    if (filled($order->next_action)) {
                        return [
                            'stale' => true,
                            'scope' => (int) $order->organization_id,
                            'stale_message' =>
                                'La sugerencia ya no está vigente porque el servicio ya tiene una siguiente acción. Recarga Jarvis antes de preparar otra propuesta.',
                        ];
                    }

                    $payload = [
                        'next_action' => trim(
                            (string) ($validated['next_action'] ?? ''),
                        ),
                        'next_action_at' =>
                            $validated['next_action_at']
                            ?? null,
                    ];
                } elseif ($action === 'service_order.stage.set') {
                    $targetStage = (string) $validated['service_stage'];

                    if ($order->stage === $targetStage) {
                        return [
                            'stale' => true,
                            'scope' => (int) $order->organization_id,
                            'stale_message' =>
                                'El servicio ya se encuentra en la etapa seleccionada. No se creó una propuesta sin cambios.',
                        ];
                    }

                    $payload = [
                        'stage' => $targetStage,
                    ];
                }

                $proposal = $gateway->proposeServiceOrderAction(
                    $request->user(),
                    $order,
                    $action,
                    $payload,
                    'Propuesta de servicio preparada manualmente desde Lectura Jarvis. El servicio permanece sin cambios hasta aprobación y segunda confirmación.',
                );

                return [
                    'stale' => false,
                    'scope' => (int) $order->organization_id,
                    'proposal' => $proposal,
                    'created' => $proposal->wasRecentlyCreated,
                ];
            },
        );

        if ($result['stale'] ?? false) {
            return redirect()
                ->route(
                    'agent-proposals.index',
                    [
                        'scope' => $result['scope'],
                        'status' => 'pending',
                    ],
                )
                ->with(
                    'agent_proposal_message',
                    $result['stale_message']
                    ?? 'La sugerencia ya no está vigente. Recarga Jarvis antes de preparar otra propuesta.',
                );
        }

        $message = ($result['created'] ?? false)
            ? 'Propuesta preparada y enviada a Pendientes. Aún no se ejecutó ningún cambio.'
            : 'Ya existía una propuesta pendiente idéntica; CENTRAL reutilizó la existente. No se ejecutó ningún cambio.';

        return redirect()
            ->route(
                'agent-proposals.index',
                [
                    'scope' => $result['scope'],
                    'status' => 'pending',
                ],
            )
            ->with(
                'agent_proposal_message',
                $message,
            );
    }

'''

controller_text = controller_text[:start] + new_prepare + controller_text[end:]
controller.write_text(controller_text)

view_text = view.read_text()
expanded_marker = 'data-expanded-actions="2.18"'

if expanded_marker not in view_text:
    anchor = "@if($priority['type'] === 'task')\n"
    if anchor not in view_text:
        raise SystemExit('ERROR: no se encontró el ancla de acciones de tarea en la vista')

    expanded_block = r'''@if($priority['type'] === 'project')
<details class="proposal-more" data-expanded-actions="2.18">
<summary>Más acciones de proyecto</summary>

<form class="proposal-prepare" method="POST" action="{{ route('agent-proposals.prepare') }}">
@csrf
<input type="hidden" name="subject_type" value="project">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="project.status.set">
<div class="proposal-field">
<label for="jarvis-project-status-{{ $priority['id'] }}">Nuevo estado</label>
<select class="proposal-input" id="jarvis-project-status-{{ $priority['id'] }}" name="project_status" required>
@foreach(\App\Models\Project::statusOptions() as $statusKey => $statusLabel)
<option value="{{ $statusKey }}">{{ $statusLabel }}</option>
@endforeach
</select>
</div>
<button class="prepare-button" type="submit" data-confirm="¿Preparar el cambio de estado? El proyecto no se modificará todavía." data-busy-label="Preparando…">Preparar estado</button>
</form>

<form class="proposal-prepare" method="POST" action="{{ route('agent-proposals.prepare') }}">
@csrf
<input type="hidden" name="subject_type" value="project">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="project.blockers.clear">
<div class="proposal-field">
<label>Bloqueos</label>
<div class="prepare-note">Prepara la limpieza de los bloqueos registrados. Si ya no existen, CENTRAL no creará una propuesta vacía.</div>
</div>
<button class="prepare-button" type="submit" data-confirm="¿Preparar la limpieza de bloqueos? El proyecto no se modificará todavía." data-busy-label="Preparando…">Preparar limpieza</button>
</form>

<form class="proposal-prepare service" method="POST" action="{{ route('agent-proposals.prepare') }}">
@csrf
<input type="hidden" name="subject_type" value="project">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="project.task.create">
<div class="proposal-field">
<label for="jarvis-project-task-{{ $priority['id'] }}">Nueva tarea vinculada</label>
<input class="proposal-input" id="jarvis-project-task-{{ $priority['id'] }}" name="project_task_title" type="text" maxlength="255" required placeholder="Ej. Validar entregable con el cliente">
</div>
<div class="proposal-field">
<label for="jarvis-project-urgency-{{ $priority['id'] }}">Urgencia</label>
<select class="proposal-input" id="jarvis-project-urgency-{{ $priority['id'] }}" name="project_task_urgency">
<option value="normal">Normal</option>
<option value="high">Alta</option>
<option value="critical">Crítica</option>
<option value="low">Baja</option>
</select>
</div>
<div class="proposal-field">
<label for="jarvis-project-due-{{ $priority['id'] }}">Vence (opcional)</label>
<input class="proposal-input" id="jarvis-project-due-{{ $priority['id'] }}" name="project_task_due_date" type="date">
</div>
<button class="prepare-button" type="submit" data-confirm="¿Preparar la creación de esta tarea? No se creará hasta aprobar y confirmar la propuesta." data-busy-label="Preparando…">Preparar tarea</button>
</form>
<div class="prepare-note">Todas estas acciones siguen el flujo propuesta → aprobación → segunda confirmación → undo.</div>
</details>
@elseif($priority['type'] === 'service_order')
<details class="proposal-more" data-expanded-actions="2.18">
<summary>Más acciones de servicio</summary>
<form class="proposal-prepare" method="POST" action="{{ route('agent-proposals.prepare') }}">
@csrf
<input type="hidden" name="subject_type" value="service_order">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="service_order.stage.set">
<div class="proposal-field">
<label for="jarvis-service-stage-{{ $priority['id'] }}">Nueva etapa</label>
<select class="proposal-input" id="jarvis-service-stage-{{ $priority['id'] }}" name="service_stage" required>
@foreach(\App\Models\ServiceOrder::stageOptions() as $stageKey => $stageLabel)
<option value="{{ $stageKey }}">{{ $stageLabel }}</option>
@endforeach
</select>
</div>
<button class="prepare-button" type="submit" data-confirm="¿Preparar el cambio de etapa? El servicio no se modificará todavía." data-busy-label="Preparando…">Preparar etapa</button>
</form>
<div class="prepare-note">La etapa solo cambiará después de aprobación humana y segunda confirmación.</div>
</details>
@endif

'''

    view_text = view_text.replace(anchor, expanded_block + anchor, 1)
    view.write_text(view_text)

print('2.18 patch aplicado correctamente')
