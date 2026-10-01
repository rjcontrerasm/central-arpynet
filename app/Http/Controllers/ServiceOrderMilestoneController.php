<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderMilestone;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceOrderMilestoneController extends Controller
{
    public function store(
        Request $request,
        ServiceOrder $serviceOrder,
    ): RedirectResponse {
        $this->authorizeOrder($request, $serviceOrder);

        $validated = $this->validatePayload($request);
        $this->validateExecutionOrder($serviceOrder, $validated);
        $assigneeId = $this->resolveAssignee(
            $request,
            $serviceOrder,
            $validated['assigned_to'] ?? null,
        );

        DB::transaction(function () use (
            $request,
            $serviceOrder,
            $validated,
            $assigneeId,
        ): void {
            $sequence = ((int) $serviceOrder
                ->milestones()
                ->withTrashed()
                ->max('sequence')) + 1;

            $task = Task::query()->create([
                'organization_id' => $serviceOrder->organization_id,
                'title' => $this->taskTitle(
                    $serviceOrder,
                    $validated['title'],
                    isset($validated['execution_order_id'])
                        ? (int) $validated['execution_order_id']
                        : null,
                ),
                'description' => $this->nullableText(
                    $validated['description'] ?? null,
                ),
                'status' => 'pending',
                'urgency' => $validated['urgency'],
                'impact' => 'normal',
                'due_at' => $this->dueAt(
                    $validated['contractual_due_date'] ?? null,
                ),
                'source' => 'service_order_milestone',
                'assigned_to' => $assigneeId,
                'created_by' => $request->user()->id,
                'visibility_scope' => $serviceOrder->work_team_id
                    ? 'teams'
                    : 'organization',
            ]);

            if ($serviceOrder->work_team_id) {
                $task->workTeams()->sync([
                    (int) $serviceOrder->work_team_id,
                ]);
            }

            ServiceOrderMilestone::query()->create([
                'service_order_id' => $serviceOrder->id,
                'task_id' => $task->id,
                'execution_order_id' =>
                    $validated['execution_order_id'] ?? null,
                'sequence' => $sequence,
                'title' => trim($validated['title']),
                'description' => $this->nullableText(
                    $validated['description'] ?? null,
                ),
                'contractual_due_date' =>
                    $validated['contractual_due_date'] ?? null,
                'amount' => $validated['amount'] ?? null,
                'notes' => $this->nullableText(
                    $validated['notes'] ?? null,
                ),
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->withFragment('cronograma')
            ->with(
                'service_milestone_success',
                'Hito creado y vinculado a una tarea.',
            );
    }

    public function update(
        Request $request,
        ServiceOrder $serviceOrder,
        ServiceOrderMilestone $milestone,
    ): RedirectResponse {
        $this->authorizeOrder($request, $serviceOrder);
        $this->assertMilestoneBelongsToOrder(
            $serviceOrder,
            $milestone,
        );

        $validated = $this->validatePayload(
            $request,
            includeDeliveryFields: true,
        );
        $this->validateExecutionOrder($serviceOrder, $validated);

        $assigneeId = $this->resolveAssignee(
            $request,
            $serviceOrder,
            $validated['assigned_to'] ?? null,
        );

        DB::transaction(function () use (
            $serviceOrder,
            $milestone,
            $validated,
            $assigneeId,
        ): void {
            $milestone->fill([
                'execution_order_id' =>
                    $validated['execution_order_id'] ?? null,
                'title' => trim($validated['title']),
                'description' => $this->nullableText(
                    $validated['description'] ?? null,
                ),
                'contractual_due_date' =>
                    $validated['contractual_due_date'] ?? null,
                'delivered_date' =>
                    $validated['delivered_date'] ?? null,
                'conformity_date' =>
                    $validated['conformity_date'] ?? null,
                'amount' => $validated['amount'] ?? null,
                'notes' => $this->nullableText(
                    $validated['notes'] ?? null,
                ),
            ])->save();

            if ($milestone->task) {
                $milestone->task->fill([
                    'title' => $this->taskTitle(
                        $serviceOrder,
                        $validated['title'],
                        isset($validated['execution_order_id'])
                            ? (int) $validated['execution_order_id']
                            : null,
                    ),
                    'description' => $this->nullableText(
                        $validated['description'] ?? null,
                    ),
                    'urgency' => $validated['urgency'],
                    'due_at' => $this->dueAt(
                        $validated['contractual_due_date'] ?? null,
                    ),
                    'assigned_to' => $assigneeId,
                    'visibility_scope' => $serviceOrder->work_team_id
                        ? 'teams'
                        : 'organization',
                ])->save();

                $milestone->task->workTeams()->sync(
                    $serviceOrder->work_team_id
                        ? [(int) $serviceOrder->work_team_id]
                        : [],
                );
            }
        });

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->withFragment('cronograma')
            ->with(
                'service_milestone_success',
                'Hito actualizado.',
            );
    }

    public function action(
        Request $request,
        ServiceOrder $serviceOrder,
        ServiceOrderMilestone $milestone,
    ): RedirectResponse {
        $this->authorizeOrder($request, $serviceOrder);
        $this->assertMilestoneBelongsToOrder(
            $serviceOrder,
            $milestone,
        );

        $validated = $request->validate([
            'action' => [
                'required',
                Rule::in([
                    'complete_task',
                    'mark_delivered',
                    'mark_conformity',
                ]),
            ],
        ]);

        if (
            $validated['action'] === 'complete_task'
            && $milestone->task
        ) {
            abort_unless(
                $milestone->task->canBeUpdatedBy($request->user()),
                403,
            );
        }

        $today = now(
            config('app.timezone', 'America/Lima'),
        )->toDateString();

        DB::transaction(function () use (
            $milestone,
            $validated,
            $today,
        ): void {
            if ($validated['action'] === 'complete_task') {
                if ($milestone->task) {
                    $milestone->task->fill([
                        'status' => 'completed',
                    ])->save();
                }

                return;
            }

            if ($validated['action'] === 'mark_delivered') {
                $milestone->fill([
                    'delivered_date' => $milestone->delivered_date
                        ?: $today,
                ])->save();

                return;
            }

            $milestone->fill([
                'delivered_date' => $milestone->delivered_date
                    ?: $today,
                'conformity_date' => $milestone->conformity_date
                    ?: $today,
            ])->save();
        });

        $message = match ($validated['action']) {
            'complete_task' => 'Tarea del hito completada.',
            'mark_delivered' => 'Hito marcado como entregado.',
            default => 'Conformidad registrada.',
        };

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->withFragment('cronograma')
            ->with('service_milestone_success', $message);
    }

    private function validateExecutionOrder(
        ServiceOrder $serviceOrder,
        array $validated,
    ): void {
        $executionOrderId = isset($validated['execution_order_id'])
            ? (int) $validated['execution_order_id']
            : null;

        if (! $executionOrderId) {
            return;
        }

        if (
            ! $serviceOrder->executionOrders()
                ->whereKey($executionOrderId)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'execution_order_id' =>
                    'La orden seleccionada no pertenece a este servicio.',
            ]);
        }
    }

    private function validatePayload(
        Request $request,
        bool $includeDeliveryFields = false,
    ): array {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'contractual_due_date' => ['nullable', 'date'],
            'execution_order_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'urgency' => [
                'required',
                Rule::in(['low', 'normal', 'high', 'critical']),
            ],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($includeDeliveryFields) {
            $rules['delivered_date'] = ['nullable', 'date'];
            $rules['conformity_date'] = [
                'nullable',
                'date',
                'after_or_equal:delivered_date',
            ];
        }

        return $request->validate($rules);
    }

    private function resolveAssignee(
        Request $request,
        ServiceOrder $serviceOrder,
        mixed $requestedAssignee,
    ): int {
        $assigneeId = (int) (
            $requestedAssignee
            ?: $serviceOrder->assigned_to
            ?: $request->user()->id
        );

        $query = User::query()
            ->whereKey($assigneeId)
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn ($organizationQuery) => $organizationQuery
                    ->where(
                        'organizations.id',
                        $serviceOrder->organization_id,
                    )
                    ->where('organization_user.is_active', true)
                    ->whereIn(
                        'organization_user.role',
                        ['owner', 'admin', 'member'],
                    ),
            );

        if ($serviceOrder->work_team_id) {
            $query->whereHas(
                'workTeams',
                fn ($teamQuery) => $teamQuery
                    ->where(
                        'work_teams.id',
                        $serviceOrder->work_team_id,
                    )
                    ->where('work_team_user.is_active', true)
                    ->whereIn(
                        'work_team_user.role',
                        ['lead', 'member'],
                    ),
            );
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                'assigned_to' =>
                    'El responsable del hito debe tener acceso operativo válido al servicio y a su equipo.',
            ]);
        }

        return $assigneeId;
    }

    private function authorizeOrder(
        Request $request,
        ServiceOrder $serviceOrder,
    ): void {
        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $serviceOrder->organization_id,
            ),
            403,
        );
    }

    private function assertMilestoneBelongsToOrder(
        ServiceOrder $serviceOrder,
        ServiceOrderMilestone $milestone,
    ): void {
        abort_unless(
            (int) $milestone->service_order_id
                === (int) $serviceOrder->id,
            404,
        );
    }

    private function dueAt(?string $date): ?CarbonImmutable
    {
        if (! $date) {
            return null;
        }

        return CarbonImmutable::parse(
            $date,
            config('app.timezone', 'America/Lima'),
        )->setTime(17, 0);
    }

    private function taskTitle(
        ServiceOrder $serviceOrder,
        string $milestoneTitle,
        ?int $executionOrderId = null,
    ): string {
        $executionNumber = $executionOrderId
            ? $serviceOrder->executionOrders()
                ->whereKey($executionOrderId)
                ->value('document_number')
            : null;

        $context = trim(
            (string) (
                $executionNumber
                ?: $serviceOrder->order_number
                ?: $serviceOrder->contract_number
                ?: $serviceOrder->title
            ),
        );

        return mb_substr(
            $context.' · '.trim($milestoneTitle),
            0,
            255,
        );
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
