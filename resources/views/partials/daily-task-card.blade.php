@php
                                $isOverdue = $task->due_at
                                    && $task->due_at->isBefore(
                                        $now->startOfDay(),
                                    );

                                $band = $task
                                    ->display_priority_band;

                                $canWriteTask = $currentUser
                                    ?->canWriteToOrganization(
                                        (int) $task->organization_id,
                                    ) ?? false;
                            @endphp

                            <div class="item" data-operational-card>
                                <div class="item-title">
                                    {{ $task->title }}
                                </div>

                                <div class="meta">
                                    {{ $task->organization?->name
                                        ?? 'Sin ámbito' }}

                                    @if ($task->due_at)
                                        ·
                                        {{ $task->due_at->format(
                                            'd/m/Y',
                                        ) }}
                                    @else
                                        · sin fecha
                                    @endif

                                    @if ($selectedWorkView === 'team')
                                        · Responsable:
                                        {{ $task->assignee?->name
                                            ?? 'Sin asignar' }}
                                    @endif
                                </div>

                                <div class="pills">
                                    @if ($isOverdue)
                                        <span class="pill overdue">
                                            Vencida
                                        </span>
                                    @endif

                                    <span
                                        class="pill {{ $band }}"
                                    >
                                        {{
                                            $task
                                                ->display_priority_label
                                        }}
                                        ·
                                        {{
                                            $task
                                                ->display_priority_score
                                        }}
                                    </span>

                                    @if ($task->recurrence_label)
                                        <span class="pill week">
                                            ↻ {{ $task->recurrence_label }}
                                        </span>
                                    @endif

                                    @if ($task->status === 'in_progress')
                                        <span class="pill today">
                                            En curso
                                        </span>
                                    @endif

                                    @if (in_array($task->urgency, ['high', 'critical'], true))
                                        <span class="pill">
                                            {{ $task->urgency === 'critical' ? 'Urgencia crítica' : 'Urgencia alta' }}
                                        </span>
                                    @endif

                                    @if (in_array($task->impact, ['high', 'critical'], true))
                                        <span class="pill">
                                            {{ $task->impact === 'critical' ? 'Impacto crítico' : 'Impacto alto' }}
                                        </span>
                                    @endif
                                </div>

                                @if ($task->next_action)
                                    <div class="next-action-current">
                                        <strong>Siguiente:</strong>
                                        {{ $task->next_action }}
                                    </div>
                                @endif

                                @if (
                                    $task->recurrence_label
                                    && $task->recurrence_next_date
                                )
                                    <div class="recurrence-note">
                                        <strong>Recurrente:</strong>
                                        {{ $task->recurrence_label }}
                                        · próxima
                                        {{ $task
                                            ->recurrence_next_date
                                            ->format('d/m/Y') }}
                                    </div>
                                @endif

                                @if ($canWriteTask)
                                    <details class="task-edit">
                                        <summary>
                                            {{ $task->next_action
                                                ? 'Cambiar próxima acción'
                                                : 'Definir próxima acción' }}
                                        </summary>

                                        <form
                                            class="next-action-form"
                                            method="POST"
                                            action="{{ route(
                                                'task-next-action.update',
                                                $task,
                                            ) }}"
                                        >
                                            @csrf
                                            <input type="hidden" name="return_to" value="daily">
                                            <input type="hidden" name="view" value="{{ $selectedWorkView }}">

                                            @if ($selectedScope)
                                                <input type="hidden" name="scope" value="{{ $selectedScope }}">
                                            @endif

                                            @if ($search !== '')
                                                <input type="hidden" name="q" value="{{ $search }}">
                                            @endif

                                            @if ($selectedPriority)
                                                <input type="hidden" name="priority" value="{{ $selectedPriority }}">
                                            @endif

                                            <input
                                                type="text"
                                                name="next_action"
                                                value="{{ $task->next_action }}"
                                                placeholder="Ej. Enviar correo al cliente"
                                                maxlength="255"
                                            >

                                            <button
                                                class="next-action-save"
                                                type="submit"
                                                data-busy-label="Guardando…"
                                            >
                                                Guardar
                                            </button>
                                        </form>
                                    </details>

                                    @php
                                        $quickActions = [
                                            'complete' => '✓ Hecho',
                                        ];

                                        if (
                                            $task->status
                                            !== 'in_progress'
                                        ) {
                                            $quickActions[
                                                'start'
                                            ] = 'En curso';
                                        }

                                        if (
                                            ! $task->due_at
                                            || ! $task->due_at
                                                ->isSameDay($now)
                                        ) {
                                            $quickActions[
                                                'today'
                                            ] = 'Hoy';
                                        }

                                        $quickActions[
                                            'tomorrow'
                                        ] = 'Mañana';

                                        $quickActions[
                                            'next_week'
                                        ] = '+1 semana';
                                    @endphp

                                    <div class="actions">
                                        @foreach (
                                            $quickActions
                                            as $action => $label
                                        )
                                            <form
                                                class="action-form"
                                                method="POST"
                                                action="{{ route(
                                                    'daily-task-action.update',
                                                    $task,
                                                ) }}"
                                            >
                                                @csrf

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="{{ $action }}"
                                                >
                                                <input
                                                    type="hidden"
                                                    name="view"
                                                    value="{{ $selectedWorkView }}"
                                                >

                                                @if ($selectedScope)
                                                    <input
                                                        type="hidden"
                                                        name="scope"
                                                        value="{{ $selectedScope }}"
                                                    >
                                                @endif

                                                @if ($search !== '')
                                                    <input
                                                        type="hidden"
                                                        name="q"
                                                        value="{{ $search }}"
                                                    >
                                                @endif

                                                @if ($selectedPriority)
                                                    <input
                                                        type="hidden"
                                                        name="priority"
                                                        value="{{ $selectedPriority }}"
                                                    >
                                                @endif

                                                <button
                                                    class="action {{
                                                        $action
                                                            === 'complete'
                                                            ? 'done'
                                                            : ''
                                                    }}"
                                                    type="submit"
                                                    data-busy-label="Aplicando…"
                                                >
                                                    {{ $label }}
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>

                                    <details class="task-edit">
                                        <summary>En espera</summary>

                                        <form
                                            class="waiting-form"
                                            method="POST"
                                            action="{{ route(
                                                'daily-task-waiting.wait',
                                                $task,
                                            ) }}"
                                        >
                                            @csrf
                                            <input type="hidden" name="view" value="{{ $selectedWorkView }}">

                                            @if ($selectedScope)
                                                <input
                                                    type="hidden"
                                                    name="scope"
                                                    value="{{ $selectedScope }}"
                                                >
                                            @endif

                                            @if ($search !== '')
                                                <input
                                                    type="hidden"
                                                    name="q"
                                                    value="{{ $search }}"
                                                >
                                            @endif

                                            @if ($selectedPriority)
                                                <input
                                                    type="hidden"
                                                    name="priority"
                                                    value="{{ $selectedPriority }}"
                                                >
                                            @endif

                                            <input
                                                type="date"
                                                name="waiting_until"
                                                value="{{ $now->addDay()->format('Y-m-d') }}"
                                                required
                                            >

                                            <input
                                                type="text"
                                                name="waiting_reason"
                                                placeholder="Esperando respuesta, aprobación..."
                                                maxlength="255"
                                                required
                                            >

                                            <button
                                                class="wait-button"
                                                type="submit"
                                            >
                                                Poner en espera
                                            </button>
                                        </form>
                                    </details>

                                    <div class="convert-links">
                                        @if ($task->recurrence_label)
                                            <a
                                                class="convert-link"
                                                href="{{ route('recurring-task-front.index') }}"
                                            >
                                                Administrar recurrencia →
                                            </a>
                                        @else
                                            <a
                                                class="convert-link"
                                                href="{{ route(
                                                    'task-conversion.show',
                                                    [
                                                        $task,
                                                        'target' =>
                                                            'recurring',
                                                    ],
                                                ) }}"
                                            >
                                                ↻ Hacer recurrente
                                            </a>
                                        @endif

                                        <a
                                            class="convert-link"
                                            href="{{ route(
                                                'task-conversion.show',
                                                $task,
                                            ) }}"
                                        >
                                            Más conversiones →
                                        </a>
                                    </div>

                                    <details class="task-edit">
                                        <summary>Más opciones</summary>

                                        <div class="lifecycle-actions">
                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'task-lifecycle.cancel',
                                                    $task,
                                                ) }}"
                                                data-confirm="¿Cancelar esta tarea? Podrás deshacer la acción."
                                            >
                                                @csrf

                                                <button
                                                    class="lifecycle-button lifecycle-cancel"
                                                    type="submit"
                                                    data-busy-label="Cancelando…"
                                                >
                                                    Cancelar
                                                </button>
                                            </form>

                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'task-lifecycle.delete',
                                                    $task,
                                                ) }}"
                                                data-confirm="¿Enviar esta tarea a la papelera? Podrás deshacer la acción."
                                            >
                                                @csrf

                                                <button
                                                    class="lifecycle-button lifecycle-delete"
                                                    type="submit"
                                                    data-busy-label="Eliminando…"
                                                >
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </details>

                                    <details class="task-edit">
                                        <summary>Editar</summary>

                                        <form
                                            class="edit-form"
                                            method="POST"
                                            action="{{ route(
                                                'daily-task-edit.update',
                                                $task,
                                            ) }}"
                                        >
                                            @csrf
                                            <input type="hidden" name="view" value="{{ $selectedWorkView }}">

                                            @if ($selectedScope)
                                                <input
                                                    type="hidden"
                                                    name="scope"
                                                    value="{{ $selectedScope }}"
                                                >
                                            @endif

                                            <div class="edit-grid">
                                                <label class="edit-field full">
                                                    Empresa / ámbito

                                                    <select
                                                        name="organization_id"
                                                        required
                                                    >
                                                        @foreach (
                                                            $organizations
                                                            as $organization
                                                        )
                                                            <option
                                                                value="{{ $organization->id }}"
                                                                @selected(
                                                                    (string) $task->organization_id
                                                                    === (string) $organization->id
                                                                )
                                                            >
                                                                {{ $organization->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </label>

                                                <label class="edit-field full">
                                                    Fecha

                                                    <input
                                                        type="date"
                                                        name="due_date"
                                                        value="{{ $task->due_at?->format('Y-m-d') }}"
                                                    >
                                                </label>

                                                <label class="edit-field">
                                                    Urgencia

                                                    <select name="urgency">
                                                        @foreach ([
                                                            'low' => 'Baja',
                                                            'normal' => 'Normal',
                                                            'high' => 'Alta',
                                                            'critical' => 'Crítica',
                                                        ] as $value => $label)
                                                            <option
                                                                value="{{ $value }}"
                                                                @selected(
                                                                    $task->urgency
                                                                    === $value
                                                                )
                                                            >
                                                                {{ $label }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </label>

                                                <label class="edit-field">
                                                    Impacto

                                                    <select name="impact">
                                                        @foreach ([
                                                            'low' => 'Bajo',
                                                            'normal' => 'Normal',
                                                            'high' => 'Alto',
                                                            'critical' => 'Crítico',
                                                        ] as $value => $label)
                                                            <option
                                                                value="{{ $value }}"
                                                                @selected(
                                                                    $task->impact
                                                                    === $value
                                                                )
                                                            >
                                                                {{ $label }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            </div>

                                            <button
                                                class="save-edit"
                                                type="submit"
                                            >
                                                Guardar cambios
                                            </button>
                                        </form>
                                    </details>
                                @endif
                            </div>
