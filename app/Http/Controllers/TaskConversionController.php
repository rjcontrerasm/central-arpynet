<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\RecurringTaskRun;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Support\GlobalUndoService;
use App\Support\RecurringTaskGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskConversionController extends Controller
{
    public function show(
        Request $request,
        Task $task,
    ): View {
        $this->authorizeTask(
            $request,
            $task,
        );

        $clients = Client::query()
            ->where(
                'organization_id',
                $task->organization_id,
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $frequencies =
            RecurringTaskRule::frequencyOptions();

        $selectedTarget = in_array(
            (string) $request->query(
                'target',
                'project',
            ),
            [
                'project',
                'service',
                'recurring',
                'waiting',
            ],
            true,
        )
            ? (string) $request->query(
                'target',
                'project',
            )
            : 'project';

        $selectedFrequency =
            array_key_exists(
                (string) $request->query(
                    'frequency',
                    'monthly',
                ),
                $frequencies,
            )
                ? (string) $request->query(
                    'frequency',
                    'monthly',
                )
                : 'monthly';

        $suggestedAnchor =
            $this->suggestedNextAnchor(
                $task,
                $selectedFrequency,
            );

        return view(
            'task-convert',
            [
                'task' => $task,
                'clients' => $clients,
                'frequencies' => $frequencies,
                'selectedTarget' =>
                    $selectedTarget,
                'selectedFrequency' =>
                    $selectedFrequency,
                'suggestedAnchor' =>
                    $suggestedAnchor,
            ],
        );
    }

    public function store(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $this->authorizeTask(
            $request,
            $task,
        );

        $validated = $request->validate([
            'target' => [
                'required',
                Rule::in([
                    'project',
                    'service',
                    'recurring',
                    'waiting',
                ]),
            ],
            'client_id' => [
                'nullable',
                'integer',
                'required_if:target,service',
            ],
            'frequency' => [
                'nullable',
                Rule::in(
                    array_keys(
                        RecurringTaskRule::frequencyOptions(),
                    ),
                ),
                'required_if:target,recurring',
            ],
            'anchor_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
                'required_if:target,recurring',
            ],
            'create_days_before' => [
                'nullable',
                'integer',
                'min:0',
                'max:90',
                'required_if:target,recurring',
            ],
            'due_time' => [
                'nullable',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                'required_if:target,recurring',
            ],
            'waiting_until' => [
                'nullable',
                'date',
            ],
            'waiting_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $before = $undo->captureTask(
            $task,
        );

        $result = DB::transaction(
            function () use (
                $request,
                $task,
                $validated,
            ): array {
                return match (
                    $validated['target']
                ) {
                    'project' =>
                        $this->toProject(
                            $request,
                            $task,
                        ),
                    'service' =>
                        $this->toService(
                            $request,
                            $task,
                            (int) $validated[
                                'client_id'
                            ],
                        ),
                    'recurring' =>
                        $this->toRecurring(
                            $request,
                            $task,
                            (string) $validated[
                                'frequency'
                            ],
                            (string) $validated[
                                'anchor_date'
                            ],
                            (int) $validated[
                                'create_days_before'
                            ],
                            (string) $validated[
                                'due_time'
                            ],
                        ),
                    'waiting' =>
                        $this->toWaiting(
                            $task,
                            $validated[
                                'waiting_until'
                            ] ?? null,
                            $validated[
                                'waiting_reason'
                            ] ?? null,
                        ),
                };
            },
        );

        $task->refresh();

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            $result['label'],
            route(
                'daily-ops.show',
                [],
                false,
            ),
            $result['cleanup']
                ?? [],
        );

        return redirect()
            ->route('daily-ops.show')
            ->with(
                'daily_action_success',
                $result['message'],
            );
    }

    private function toProject(
        Request $request,
        Task $task,
    ): array {
        $project = Project::query()
            ->create([
                'organization_id' =>
                    $task->organization_id,
                'name' => $task->title,
                'description' =>
                    $task->description,
                'type' => 'project',
                'horizon' => 'short',
                'status' => 'active',
                'start_date' =>
                    now()->toDateString(),
                'target_date' =>
                    $task->due_at
                        ?->toDateString(),
                'next_action' =>
                    $task->next_action,
                'created_by' =>
                    $request->user()->id,
                'is_private' =>
                    (bool) $task->is_private,
            ]);

        $task->forceFill([
            'project_id' => $project->id,
            'status' =>
                $task->status === 'pending'
                    ? 'in_progress'
                    : $task->status,
        ])->save();

        $project->refresh();

        return [
            'message' =>
                'Tarea convertida en proyecto y vinculada como primera tarea.',
            'label' =>
                'Tarea convertida en proyecto',
            'cleanup' => [
                'kind' => 'project',
                'id' => $project->id,
                'updated_at' =>
                    $project->updated_at
                        ?->toIso8601String(),
            ],
        ];
    }

    private function toService(
        Request $request,
        Task $task,
        int $clientId,
    ): array {
        $client = Client::query()
            ->whereKey($clientId)
            ->where(
                'organization_id',
                $task->organization_id,
            )
            ->where('is_active', true)
            ->first();

        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' =>
                    'El cliente no pertenece al ámbito de la tarea.',
            ]);
        }

        $service = ServiceOrder::query()
            ->create([
                'organization_id' =>
                    $task->organization_id,
                'client_id' =>
                    $client->id,
                'title' =>
                    $task->title,
                'description' =>
                    $task->description,
                'stage' =>
                    'opportunity',
                'next_action' =>
                    $task->next_action,
                'next_action_at' =>
                    $task->due_at,
                'created_by' =>
                    $request->user()->id,
            ]);

        $task->forceFill([
            'status' => 'completed',
            'external_system' =>
                'central_conversion',
            'external_id' =>
                'service:'.$service->id,
        ])->save();

        $service->refresh();

        return [
            'message' =>
                'Tarea convertida en servicio/oportunidad.',
            'label' =>
                'Tarea convertida en servicio',
            'cleanup' => [
                'kind' => 'service',
                'id' => $service->id,
                'updated_at' =>
                    $service->updated_at
                        ?->toIso8601String(),
            ],
        ];
    }

    private function toRecurring(
        Request $request,
        Task $task,
        string $frequency,
        string $anchorDate,
        int $createDaysBefore,
        string $dueTime,
    ): array {
        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        if (
            $task->recurringRun()
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'target' =>
                    'Esta tarea ya pertenece a una recurrencia.',
            ]);
        }

        $anchor = CarbonImmutable::parse(
            $anchorDate,
            $timezone,
        )->startOfDay();

        $rule = RecurringTaskRule::withoutEvents(
            fn (): RecurringTaskRule =>
                RecurringTaskRule::query()
                    ->create([
                'organization_id' =>
                    $task->organization_id,
                'project_id' =>
                    $task->project_id,
                'title' =>
                    $task->title,
                'description' =>
                    $task->description,
                'next_action' =>
                    $task->next_action,
                'frequency' =>
                    $frequency,
                        'anchor_date' =>
                            $anchor->toDateString(),
                        'create_days_before' =>
                            $createDaysBefore,
                        'due_time' => $dueTime,
                        'urgency' =>
                            $task->urgency,
                        'impact' =>
                            $task->impact,
                        'is_private' =>
                            (bool) $task->is_private,
                        'is_active' => true,
                        'assigned_to' =>
                            $task->assigned_to
                            ?? $request->user()->id,
                        'created_by' =>
                            $request->user()->id,
                    ]),
        );

        $seedDate = $task->due_at
            ? CarbonImmutable::parse(
                $task->due_at,
                $timezone,
            )->startOfDay()
            : CarbonImmutable::now(
                $timezone,
            )->startOfDay();

        RecurringTaskRun::query()->create([
            'recurring_task_rule_id' =>
                $rule->id,
            'organization_id' =>
                $rule->organization_id,
            'scheduled_for' =>
                $seedDate->toDateString(),
            'task_id' => $task->id,
            'generated_at' => now(),
        ]);

        app(
            RecurringTaskGenerator::class,
        )->generateFor(
            $rule->fresh(),
            now(),
        );

        $rule->refresh();

        $frequencyLabel =
            RecurringTaskRule::frequencyOptions()[
                $frequency
            ] ?? $frequency;

        return [
            'message' =>
                'Recurrencia '.$frequencyLabel
                .' creada. Próxima ocurrencia: '
                .$anchor->format('d/m/Y')
                .'. La tarea actual se conserva.',
            'label' =>
                'Recurrencia creada desde tarea',
            'cleanup' => [
                'kind' => 'recurring',
                'id' => $rule->id,
                'updated_at' =>
                    $rule->updated_at
                        ?->toIso8601String(),
            ],
        ];
    }

    private function toWaiting(
        Task $task,
        ?string $waitingUntil,
        ?string $reason,
    ): array {
        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $until = $waitingUntil
            ? CarbonImmutable::parse(
                $waitingUntil,
                $timezone,
            )->setTime(17, 0)
            : $task->due_at;

        $task->forceFill([
            'status' => 'waiting',
            'waiting_since' =>
                CarbonImmutable::now(
                    $timezone,
                ),
            'waiting_until' => $until,
            'waiting_reason' =>
                trim((string) $reason)
                    !== ''
                    ? trim(
                        (string) $reason,
                    )
                    : 'Seguimiento pendiente',
            'due_at' => null,
        ])->save();

        return [
            'message' =>
                'Tarea convertida en seguimiento en espera.',
            'label' =>
                'Tarea convertida en espera',
            'cleanup' => [],
        ];
    }

    private function suggestedNextAnchor(
        Task $task,
        string $frequency,
    ): CarbonImmutable {
        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $today = CarbonImmutable::now(
            $timezone,
        )->startOfDay();

        $base = $task->due_at
            ? CarbonImmutable::parse(
                $task->due_at,
                $timezone,
            )->startOfDay()
            : $today;

        $candidate = $this->nextAnchor(
            $base,
            $frequency,
        );

        while ($candidate->lt($today)) {
            $candidate = $this->nextAnchor(
                $candidate,
                $frequency,
            );
        }

        return $candidate;
    }
    private function nextAnchor(
        CarbonImmutable $now,
        string $frequency,
    ): CarbonImmutable {
        return (match ($frequency) {
            'daily' => $now->addDay(),
            'weekly' => $now->addWeek(),
            'bimonthly' =>
                $now->addMonthsNoOverflow(2),
            'quarterly' =>
                $now->addMonthsNoOverflow(3),
            'semiannual' =>
                $now->addMonthsNoOverflow(6),
            'annual' =>
                $now->addYearNoOverflow(),
            default =>
                $now->addMonthNoOverflow(),
        })->startOfDay();
    }

    private function authorizeTask(
        Request $request,
        Task $task,
    ): void {
        $allowed = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->where(
                'organization_id',
                $task->organization_id,
            )
            ->where(
                'is_active',
                true,
            )
            ->exists();

        abort_unless(
            $allowed,
            403,
        );
    }
}
