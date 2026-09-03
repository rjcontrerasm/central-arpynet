<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\ServiceOrder;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskConversionController extends Controller
{
    public function show(Request $request, Task $task): View
    {
        $this->authorizeTask($request, $task);

        $clients = Client::query()
            ->where('organization_id', $task->organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('task-convert', [
            'task' => $task,
            'clients' => $clients,
            'frequencies' => RecurringTaskRule::frequencyOptions(),
        ]);
    }

    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $task);

        $validated = $request->validate([
            'target' => [
                'required',
                Rule::in(['project', 'service', 'recurring', 'waiting']),
            ],
            'client_id' => ['nullable', 'integer', 'required_if:target,service'],
            'frequency' => [
                'nullable',
                Rule::in(array_keys(RecurringTaskRule::frequencyOptions())),
                'required_if:target,recurring',
            ],
            'waiting_until' => ['nullable', 'date'],
            'waiting_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $message = DB::transaction(function () use ($request, $task, $validated): string {
            return match ($validated['target']) {
                'project' => $this->toProject($request, $task),
                'service' => $this->toService(
                    $request,
                    $task,
                    (int) $validated['client_id'],
                ),
                'recurring' => $this->toRecurring(
                    $request,
                    $task,
                    (string) $validated['frequency'],
                ),
                'waiting' => $this->toWaiting(
                    $task,
                    $validated['waiting_until'] ?? null,
                    $validated['waiting_reason'] ?? null,
                ),
            };
        });

        return redirect()
            ->route('daily-ops.show')
            ->with('daily_action_success', $message);
    }

    private function toProject(Request $request, Task $task): string
    {
        $project = Project::query()->create([
            'organization_id' => $task->organization_id,
            'name' => $task->title,
            'description' => $task->description,
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'target_date' => $task->due_at?->toDateString(),
            'next_action' => $task->next_action,
            'created_by' => $request->user()->id,
            'is_private' => (bool) $task->is_private,
        ]);

        $task->forceFill([
            'project_id' => $project->id,
            'status' => $task->status === 'pending'
                ? 'in_progress'
                : $task->status,
        ])->save();

        return 'Tarea convertida en proyecto y vinculada como primera tarea.';
    }

    private function toService(
        Request $request,
        Task $task,
        int $clientId,
    ): string {
        $client = Client::query()
            ->whereKey($clientId)
            ->where('organization_id', $task->organization_id)
            ->where('is_active', true)
            ->first();

        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente no pertenece al ámbito de la tarea.',
            ]);
        }

        $service = ServiceOrder::query()->create([
            'organization_id' => $task->organization_id,
            'client_id' => $client->id,
            'title' => $task->title,
            'description' => $task->description,
            'stage' => 'opportunity',
            'next_action' => $task->next_action,
            'next_action_at' => $task->due_at,
            'created_by' => $request->user()->id,
        ]);

        $task->forceFill([
            'status' => 'completed',
            'external_system' => 'central_conversion',
            'external_id' => 'service:'.$service->id,
        ])->save();

        return 'Tarea convertida en servicio/oportunidad.';
    }

    private function toRecurring(
        Request $request,
        Task $task,
        string $frequency,
    ): string {
        $timezone = config('app.timezone', 'America/Lima');
        $anchor = $this->nextAnchor(
            CarbonImmutable::now($timezone),
            $frequency,
        );

        RecurringTaskRule::query()->create([
            'organization_id' => $task->organization_id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'description' => $task->description,
            'next_action' => $task->next_action,
            'frequency' => $frequency,
            'anchor_date' => $anchor->toDateString(),
            'create_days_before' => 0,
            'due_time' => $task->due_at?->format('H:i') ?? '17:00',
            'urgency' => $task->urgency,
            'impact' => $task->impact,
            'is_private' => (bool) $task->is_private,
            'is_active' => true,
            'assigned_to' => $task->assigned_to ?? $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        return 'Recurrencia creada desde la siguiente ocurrencia; la tarea actual se conserva.';
    }

    private function toWaiting(
        Task $task,
        ?string $waitingUntil,
        ?string $reason,
    ): string {
        $timezone = config('app.timezone', 'America/Lima');

        $until = $waitingUntil
            ? CarbonImmutable::parse($waitingUntil, $timezone)->setTime(17, 0)
            : $task->due_at;

        $task->forceFill([
            'status' => 'waiting',
            'waiting_since' => CarbonImmutable::now($timezone),
            'waiting_until' => $until,
            'waiting_reason' => trim((string) $reason) !== ''
                ? trim((string) $reason)
                : 'Seguimiento pendiente',
            'due_at' => null,
        ])->save();

        return 'Tarea convertida en seguimiento en espera.';
    }

    private function nextAnchor(
        CarbonImmutable $now,
        string $frequency,
    ): CarbonImmutable {
        return (match ($frequency) {
            'daily' => $now->addDay(),
            'weekly' => $now->addWeek(),
            'bimonthly' => $now->addMonthsNoOverflow(2),
            'quarterly' => $now->addMonthsNoOverflow(3),
            'semiannual' => $now->addMonthsNoOverflow(6),
            'annual' => $now->addYearNoOverflow(),
            default => $now->addMonthNoOverflow(),
        })->startOfDay();
    }

    private function authorizeTask(Request $request, Task $task): void
    {
        $allowed = DB::table('organization_user')
            ->where('user_id', $request->user()->id)
            ->where('organization_id', $task->organization_id)
            ->where('is_active', true)
            ->exists();

        abort_unless($allowed, 403);
    }
}
