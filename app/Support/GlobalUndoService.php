<?php

namespace App\Support;

use App\Models\ObligationOccurrence;
use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\UndoAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GlobalUndoService
{
    public const SESSION_KEY =
        'central_global_undo_id';

    private const SERVICE_ORDER_FIELDS = [
        'organization_id',
        'client_id',
        'title',
        'description',
        'stage',
        'stage_changed_at',
        'quotation_number',
        'quotation_date',
        'order_number',
        'order_received_date',
        'start_date',
        'end_date',
        'report_submitted_date',
        'conformity_date',
        'invoice_number',
        'invoice_date',
        'invoice_due_date',
        'paid_date',
        'closed_date',
        'amount',
        'invoice_amount',
        'currency',
        'includes_tax',
        'next_action',
        'next_action_at',
        'drive_url',
        'notes',
        'last_activity_at',
        'created_by',
    ];

    private const OBLIGATION_OCCURRENCE_FIELDS = [
        'recurring_obligation_id',
        'organization_id',
        'due_date',
        'status',
        'expected_amount',
        'actual_amount',
        'currency',
        'paid_date',
        'payment_reference',
        'receipt_url',
        'notes',
        'completed_at',
    ];

    private const TASK_FIELDS = [
        'organization_id',
        'project_id',
        'parent_task_id',
        'title',
        'description',
        'next_action',
        'status',
        'urgency',
        'impact',
        'start_at',
        'due_at',
        'waiting_since',
        'waiting_reason',
        'waiting_until',
        'completed_at',
        'waiting_for',
        'source',
        'external_system',
        'external_id',
        'assigned_to',
        'created_by',
        'is_private',
        'sort_order',
        'deleted_at',
    ];

    public function captureTask(
        Task $task,
    ): array {
        $raw = $task->getAttributes();

        return collect(
            self::TASK_FIELDS,
        )
            ->mapWithKeys(
                fn (string $field): array => [
                    $field =>
                        $raw[$field] ?? null,
                ],
            )
            ->all();
    }

    public function rememberTaskMutation(
        User $user,
        Task $task,
        array $beforeState,
        string $label,
        ?string $returnUrl = null,
        array $cleanup = [],
    ): ?UndoAction {
        $fresh = Task::withTrashed()
            ->find($task->id);

        if (! $fresh) {
            return null;
        }

        return $this->remember(
            $user,
            'task_mutation',
            $label,
            'task',
            $fresh->id,
            [
                'before' => $beforeState,
                'expected' =>
                    $this->taskFingerprint(
                        $fresh,
                    ),
                'cleanup' => $cleanup,
            ],
            $returnUrl,
        );
    }

    public function rememberTaskCreated(
        User $user,
        Task $task,
        string $label =
            'Tarea creada',
        ?string $returnUrl = null,
    ): ?UndoAction {
        $fresh = Task::withTrashed()
            ->find($task->id);

        if (! $fresh) {
            return null;
        }

        return $this->remember(
            $user,
            'task_created',
            $label,
            'task',
            $fresh->id,
            [
                'expected' =>
                    $this->taskFingerprint(
                        $fresh,
                    ),
            ],
            $returnUrl,
        );
    }

    public function captureServiceOrder(
        ServiceOrder $serviceOrder,
    ): array {
        $raw = $serviceOrder->getAttributes();

        return collect(
            self::SERVICE_ORDER_FIELDS,
        )
            ->mapWithKeys(
                fn (string $field): array => [
                    $field =>
                        $raw[$field] ?? null,
                ],
            )
            ->all();
    }

    public function rememberServiceOrderMutation(
        User $user,
        ServiceOrder $serviceOrder,
        array $beforeState,
        string $label,
        ?string $returnUrl = null,
    ): ?UndoAction {
        $fresh = ServiceOrder::query()
            ->find($serviceOrder->id);

        if (! $fresh) {
            return null;
        }

        return $this->remember(
            $user,
            'service_mutation',
            $label,
            'service_order',
            $fresh->id,
            [
                'before' => $beforeState,
                'expected' =>
                    $this->updatedAtFingerprint(
                        $fresh,
                    ),
            ],
            $returnUrl,
        );
    }

    public function captureObligationOccurrence(
        ObligationOccurrence $occurrence,
    ): array {
        $raw = $occurrence->getAttributes();

        return collect(
            self::OBLIGATION_OCCURRENCE_FIELDS,
        )
            ->mapWithKeys(
                fn (string $field): array => [
                    $field =>
                        $raw[$field] ?? null,
                ],
            )
            ->all();
    }

    public function rememberObligationMutation(
        User $user,
        ObligationOccurrence $occurrence,
        array $beforeState,
        string $label,
        ?string $returnUrl = null,
    ): ?UndoAction {
        $fresh = ObligationOccurrence::query()
            ->find($occurrence->id);

        if (! $fresh) {
            return null;
        }

        return $this->remember(
            $user,
            'obligation_mutation',
            $label,
            'obligation_occurrence',
            $fresh->id,
            [
                'before' => $beforeState,
                'expected' =>
                    $this->updatedAtFingerprint(
                        $fresh,
                    ),
            ],
            $returnUrl,
        );
    }

    public function current(
        User $user,
    ): ?UndoAction {
        if (! Schema::hasTable(
            'undo_actions',
        )) {
            return null;
        }

        $id = (int) session()->get(
            self::SESSION_KEY,
            0,
        );

        $action = $id > 0
            ? UndoAction::query()
                ->find($id)
            : null;

        if (
            ! $action
            || $action->user_id
                !== $user->id
        ) {
            $action = UndoAction::query()
                ->where(
                    'user_id',
                    $user->id,
                )
                ->whereNull('undone_at')
                ->whereNull('superseded_at')
                ->latest('id')
                ->first();
        }

        if (
            ! $action
            || $action->undone_at
            || $action->superseded_at
            || $action->expires_at->isPast()
        ) {
            session()->forget(
                self::SESSION_KEY,
            );

            return null;
        }

        session()->put(
            self::SESSION_KEY,
            $action->id,
        );

        return $action;
    }

    public function undo(
        User $user,
        ?int $requestedId = null,
    ): array {
        $id = $requestedId
            ?: (int) session()->get(
                self::SESSION_KEY,
                0,
            );

        if ($id <= 0) {
            return [
                'ok' => false,
                'message' =>
                    'Ya no hay una acción para deshacer.',
                'return_url' =>
                    route(
                        'daily-ops.show',
                        [],
                        false,
                    ),
            ];
        }

        $action = UndoAction::query()
            ->find($id);

        if (! $action) {
            session()->forget(
                self::SESSION_KEY,
            );

            return [
                'ok' => false,
                'message' =>
                    'La acción para deshacer ya no está disponible.',
                'return_url' =>
                    route(
                        'daily-ops.show',
                        [],
                        false,
                    ),
            ];
        }

        abort_unless(
            $action->user_id
                === $user->id,
            403,
        );

        if (
            $action->undone_at
            || $action->superseded_at
            || $action->expires_at->isPast()
        ) {
            session()->forget(
                self::SESSION_KEY,
            );

            return [
                'ok' => false,
                'message' =>
                    'La opción de deshacer expiró o fue reemplazada.',
                'return_url' =>
                    $this->safeReturnUrl(
                        $action->return_url,
                    ),
            ];
        }

        $result = DB::transaction(
            function () use (
                $user,
                $action,
            ): array {
                $locked = UndoAction::query()
                    ->whereKey($action->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $locked->undone_at
                    || $locked->superseded_at
                    || $locked->expires_at
                        ->isPast()
                ) {
                    return [
                        'ok' => false,
                        'message' =>
                            'La acción ya no puede deshacerse.',
                        'return_url' =>
                            $this->safeReturnUrl(
                                $locked->return_url,
                            ),
                    ];
                }

                $applied = match (
                    $locked->action_type
                ) {
                    'task_mutation' =>
                        $this->undoTaskMutation(
                            $user,
                            $locked,
                        ),
                    'task_created' =>
                        $this->undoTaskCreated(
                            $user,
                            $locked,
                        ),
                    'service_mutation' =>
                        $this->undoServiceMutation(
                            $user,
                            $locked,
                        ),
                    'obligation_mutation' =>
                        $this->undoObligationMutation(
                            $user,
                            $locked,
                        ),
                    default => [
                        'ok' => false,
                        'message' =>
                            'Esta acción no tiene un reversor seguro.',
                    ],
                };

                if (! $applied['ok']) {
                    $locked->forceFill([
                        'superseded_at' =>
                            now(),
                    ])->save();

                    return [
                        ...$applied,
                        'return_url' =>
                            $this->safeReturnUrl(
                                $locked->return_url,
                            ),
                    ];
                }

                $locked->forceFill([
                    'undone_at' => now(),
                ])->save();

                return [
                    'ok' => true,
                    'message' => 'Acción deshecha.',
                    'return_url' =>
                        $this->safeReturnUrl(
                            $locked->return_url,
                        ),
                ];
            },
        );

        session()->forget(
            self::SESSION_KEY,
        );

        return $result;
    }

    public function invalidateCurrent(
        User $user,
    ): void {
        if (! Schema::hasTable(
            'undo_actions',
        )) {
            session()->forget(
                self::SESSION_KEY,
            );

            return;
        }

        UndoAction::query()
            ->where('user_id', $user->id)
            ->whereNull('undone_at')
            ->whereNull('superseded_at')
            ->update([
                'superseded_at' => now(),
            ]);

        session()->forget(
            self::SESSION_KEY,
        );
    }

    private function remember(
        User $user,
        string $actionType,
        string $label,
        ?string $entityType,
        ?int $entityId,
        array $payload,
        ?string $returnUrl,
    ): ?UndoAction {
        if (! Schema::hasTable(
            'undo_actions',
        )) {
            return null;
        }

        $action = DB::transaction(
            function () use (
                $user,
                $actionType,
                $label,
                $entityType,
                $entityId,
                $payload,
                $returnUrl,
            ): UndoAction {
                UndoAction::query()
                    ->where(
                        'user_id',
                        $user->id,
                    )
                    ->whereNull('undone_at')
                    ->whereNull(
                        'superseded_at',
                    )
                    ->update([
                        'superseded_at' =>
                            now(),
                    ]);

                return UndoAction::query()
                    ->create([
                        'user_id' =>
                            $user->id,
                        'action_type' =>
                            $actionType,
                        'label' =>
                            mb_substr(
                                trim($label),
                                0,
                                180,
                            ),
                        'entity_type' =>
                            $entityType,
                        'entity_id' =>
                            $entityId,
                        'payload' =>
                            $payload,
                        'return_url' =>
                            $this->safeReturnUrl(
                                $returnUrl,
                            ),
                        'expires_at' =>
                            CarbonImmutable::now()
                                ->addMinutes(10),
                    ]);
            },
        );

        session()->put(
            self::SESSION_KEY,
            $action->id,
        );

        session()->forget(
            'daily_action_undo',
        );

        return $action;
    }

    private function undoTaskMutation(
        User $user,
        UndoAction $action,
    ): array {
        $payload = is_array(
            $action->payload,
        )
            ? $action->payload
            : [];

        $task = Task::withTrashed()
            ->find($action->entity_id);

        if (! $task) {
            return [
                'ok' => false,
                'message' =>
                    'La tarea ya no está disponible.',
            ];
        }

        $before = is_array(
            $payload['before'] ?? null,
        )
            ? $payload['before']
            : [];

        $expected = is_array(
            $payload['expected']
                ?? null,
        )
            ? $payload['expected']
            : [];

        if (
            ! $this->fingerprintMatches(
                $task,
                $expected,
            )
        ) {
            return [
                'ok' => false,
                'message' =>
                    'La tarea cambió después de esa acción; ya no es seguro deshacerla automáticamente.',
            ];
        }

        if (
            ! $this->authorizedOrganization(
                $user,
                $task->organization_id,
            )
            || ! $this->authorizedOrganization(
                $user,
                (int) (
                    $before[
                        'organization_id'
                    ]
                    ?? 0
                ),
            )
        ) {
            abort(403);
        }

        $cleanup = is_array(
            $payload['cleanup'] ?? null,
        )
            ? $payload['cleanup']
            : [];

        $cleanupCheck =
            $this->validateCleanup(
                $task,
                $cleanup,
            );

        if (! $cleanupCheck['ok']) {
            return $cleanupCheck;
        }

        $this->applyTaskState(
            $task,
            $before,
        );

        $this->performCleanup(
            $cleanup,
        );

        return ['ok' => true];
    }

    private function undoTaskCreated(
        User $user,
        UndoAction $action,
    ): array {
        $task = Task::withTrashed()
            ->find($action->entity_id);

        if (! $task) {
            return [
                'ok' => false,
                'message' =>
                    'La tarea ya no está disponible.',
            ];
        }

        if (
            ! $this->authorizedOrganization(
                $user,
                $task->organization_id,
            )
        ) {
            abort(403);
        }

        $expected = is_array(
            $action->payload['expected']
                ?? null,
        )
            ? $action->payload[
                'expected'
            ]
            : [];

        if (
            ! $this->fingerprintMatches(
                $task,
                $expected,
            )
        ) {
            return [
                'ok' => false,
                'message' =>
                    'La tarea fue modificada después de crearla; no se eliminará automáticamente.',
            ];
        }

        if ($task->trashed()) {
            return [
                'ok' => false,
                'message' =>
                    'La tarea ya está en la papelera.',
            ];
        }

        $task->delete();

        return ['ok' => true];
    }

    private function undoServiceMutation(
        User $user,
        UndoAction $action,
    ): array {
        $payload = is_array(
            $action->payload,
        )
            ? $action->payload
            : [];

        $service = ServiceOrder::query()
            ->find($action->entity_id);

        if (! $service) {
            return [
                'ok' => false,
                'message' =>
                    'El servicio ya no está disponible.',
            ];
        }

        $before = is_array(
            $payload['before'] ?? null,
        )
            ? $payload['before']
            : [];

        $expected = is_array(
            $payload['expected'] ?? null,
        )
            ? $payload['expected']
            : [];

        if (
            ! $this->updatedAtMatches(
                $service,
                $expected,
            )
        ) {
            return [
                'ok' => false,
                'message' =>
                    'El servicio cambió después de esa acción; ya no es seguro deshacerla automáticamente.',
            ];
        }

        if (
            ! $this->authorizedOrganization(
                $user,
                $service->organization_id,
            )
            || ! $this->authorizedOrganization(
                $user,
                (int) (
                    $before['organization_id']
                    ?? 0
                ),
            )
        ) {
            abort(403);
        }

        $restore = collect(
            self::SERVICE_ORDER_FIELDS,
        )
            ->mapWithKeys(
                fn (string $field): array => [
                    $field =>
                        $before[$field] ?? null,
                ],
            )
            ->all();

        $stageChangedAt =
            $restore['stage_changed_at']
            ?? null;

        $lastActivityAt =
            $restore['last_activity_at']
            ?? null;

        $service->forceFill(
            $restore,
        )->save();

        /*
         * ServiceOrder updates activity timestamps
         * through model hooks. Restore those two
         * historical timestamps quietly after the
         * audited business-state rollback.
         */
        $service->forceFill([
            'stage_changed_at' =>
                $stageChangedAt,
            'last_activity_at' =>
                $lastActivityAt,
        ])->saveQuietly();

        return ['ok' => true];
    }

    private function undoObligationMutation(
        User $user,
        UndoAction $action,
    ): array {
        $payload = is_array(
            $action->payload,
        )
            ? $action->payload
            : [];

        $occurrence =
            ObligationOccurrence::query()
                ->find($action->entity_id);

        if (! $occurrence) {
            return [
                'ok' => false,
                'message' =>
                    'El vencimiento ya no está disponible.',
            ];
        }

        $before = is_array(
            $payload['before'] ?? null,
        )
            ? $payload['before']
            : [];

        $expected = is_array(
            $payload['expected'] ?? null,
        )
            ? $payload['expected']
            : [];

        if (
            ! $this->updatedAtMatches(
                $occurrence,
                $expected,
            )
        ) {
            return [
                'ok' => false,
                'message' =>
                    'El vencimiento cambió después de esa acción; ya no es seguro deshacerla automáticamente.',
            ];
        }

        if (
            ! $this->authorizedOrganization(
                $user,
                $occurrence->organization_id,
            )
            || ! $this->authorizedOrganization(
                $user,
                (int) (
                    $before['organization_id']
                    ?? 0
                ),
            )
        ) {
            abort(403);
        }

        $restore = collect(
            self::OBLIGATION_OCCURRENCE_FIELDS,
        )
            ->mapWithKeys(
                fn (string $field): array => [
                    $field =>
                        $before[$field] ?? null,
                ],
            )
            ->all();

        $occurrence->forceFill(
            $restore,
        )->save();

        return ['ok' => true];
    }

    private function updatedAtFingerprint(
        object $model,
    ): array {
        return [
            'updated_at' =>
                $model->updated_at
                    ?->toIso8601String(),
        ];
    }

    private function updatedAtMatches(
        object $model,
        array $expected,
    ): bool {
        return (
            $model->updated_at
                ?->toIso8601String()
        ) === (
            $expected['updated_at']
                ?? null
        );
    }

    private function applyTaskState(
        Task $task,
        array $state,
    ): void {
        $desiredDeleted =
            ! empty(
                $state['deleted_at']
                    ?? null
            );

        $attributes = collect(
            self::TASK_FIELDS,
        )
            ->reject(
                fn (string $field): bool =>
                    $field === 'deleted_at',
            )
            ->mapWithKeys(
                fn (string $field): array => [
                    $field =>
                        $state[$field]
                        ?? null,
                ],
            )
            ->all();

        if ($task->trashed()) {
            $task->restore();
        }

        $task->forceFill(
            $attributes,
        )->save();

        if ($desiredDeleted) {
            $task->delete();
        }
    }

    private function taskFingerprint(
        Task $task,
    ): array {
        return [
            'updated_at' =>
                $task->updated_at
                    ?->toIso8601String(),
            'deleted_at' =>
                $task->deleted_at
                    ?->toIso8601String(),
        ];
    }

    private function fingerprintMatches(
        Task $task,
        array $expected,
    ): bool {
        $current =
            $this->taskFingerprint(
                $task,
            );

        return (
            $current['updated_at']
                ?? null
        ) === (
            $expected['updated_at']
                ?? null
        )
        && (
            $current['deleted_at']
                ?? null
        ) === (
            $expected['deleted_at']
                ?? null
        );
    }

    private function validateCleanup(
        Task $task,
        array $cleanup,
    ): array {
        if ($cleanup === []) {
            return ['ok' => true];
        }

        $kind = (string) (
            $cleanup['kind'] ?? ''
        );

        $id = (int) (
            $cleanup['id'] ?? 0
        );

        $expectedUpdatedAt =
            $cleanup[
                'updated_at'
            ] ?? null;

        if (
            $kind === ''
            || $id <= 0
        ) {
            return [
                'ok' => false,
                'message' =>
                    'No se pudo validar la reversión de la conversión.',
            ];
        }

        if ($kind === 'project') {
            $project = Project::query()
                ->find($id);

            if (! $project) {
                return [
                    'ok' => false,
                    'message' =>
                        'El proyecto creado ya no está disponible.',
                ];
            }

            if (
                $project->updated_at
                    ?->toIso8601String()
                !== $expectedUpdatedAt
            ) {
                return [
                    'ok' => false,
                    'message' =>
                        'El proyecto recibió cambios; ya no es seguro eliminarlo automáticamente.',
                ];
            }

            $hasOtherTasks =
                $project->tasks()
                    ->where(
                        'tasks.id',
                        '!=',
                        $task->id,
                    )
                    ->exists();

            if ($hasOtherTasks) {
                return [
                    'ok' => false,
                    'message' =>
                        'El proyecto ya tiene otras tareas; no puede deshacerse automáticamente.',
                ];
            }

            return ['ok' => true];
        }

        if ($kind === 'service') {
            $service =
                ServiceOrder::query()
                    ->find($id);

            if (! $service) {
                return [
                    'ok' => false,
                    'message' =>
                        'El servicio creado ya no está disponible.',
                ];
            }

            if (
                $service->updated_at
                    ?->toIso8601String()
                !== $expectedUpdatedAt
            ) {
                return [
                    'ok' => false,
                    'message' =>
                        'El servicio recibió cambios; ya no es seguro eliminarlo automáticamente.',
                ];
            }

            return ['ok' => true];
        }

        if ($kind === 'recurring') {
            $rule =
                RecurringTaskRule::query()
                    ->find($id);

            if (! $rule) {
                return [
                    'ok' => false,
                    'message' =>
                        'La recurrencia creada ya no está disponible.',
                ];
            }

            if (
                $rule->updated_at
                    ?->toIso8601String()
                !== $expectedUpdatedAt
                || $rule->runs()->exists()
            ) {
                return [
                    'ok' => false,
                    'message' =>
                        'La recurrencia ya tuvo actividad; no puede deshacerse automáticamente.',
                ];
            }

            return ['ok' => true];
        }

        return [
            'ok' => false,
            'message' =>
                'Tipo de reversión no reconocido.',
        ];
    }

    private function performCleanup(
        array $cleanup,
    ): void {
        if ($cleanup === []) {
            return;
        }

        $kind = (string) (
            $cleanup['kind'] ?? ''
        );

        $id = (int) (
            $cleanup['id'] ?? 0
        );

        match ($kind) {
            'project' =>
                Project::query()
                    ->find($id)
                    ?->delete(),
            'service' =>
                ServiceOrder::query()
                    ->find($id)
                    ?->delete(),
            'recurring' =>
                RecurringTaskRule::query()
                    ->find($id)
                    ?->delete(),
            default => null,
        };
    }

    private function authorizedOrganization(
        User $user,
        int $organizationId,
    ): bool {
        if ($organizationId <= 0) {
            return false;
        }

        return DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $user->id,
            )
            ->where(
                'organization_id',
                $organizationId,
            )
            ->where(
                'is_active',
                true,
            )
            ->exists();
    }

    private function safeReturnUrl(
        ?string $url,
    ): string {
        $fallback = route(
            'daily-ops.show',
            [],
            false,
        );

        $url = trim(
            (string) $url,
        );

        if (
            $url === ''
            || ! str_starts_with(
                $url,
                '/',
            )
            || str_starts_with(
                $url,
                '//',
            )
        ) {
            return $fallback;
        }

        return mb_substr(
            $url,
            0,
            500,
        );
    }
}
