@php
    $task = $row['oldest_task'];

    $canWriteTask = $currentUser
        ?->canWriteToOrganization(
            (int) $task->organization_id,
        ) ?? false;

    $ruleId = $row['rule_id'];
@endphp

<div
    class="item recurring-overdue-group"
    data-operational-card
>
    <div class="item-title">
        {{ $task->title }}
    </div>

    <div class="meta">
        {{ $task->organization?->name
            ?? 'Sin ámbito' }}
        · {{ $row['count'] }}
        {{ $row['count'] === 1
            ? 'pendiente vencida'
            : 'pendientes vencidas' }}
    </div>

    <div class="pills">
        <span class="pill overdue">
            Vencidas × {{ $row['count'] }}
        </span>

        @if ($task->recurrence_label)
            <span class="pill week">
                ↻ {{ $task->recurrence_label }}
            </span>
        @endif
    </div>

    <div class="recurrence-note">
        <strong>Acumuladas:</strong>
        {{ $row['oldest_due_at']?->format('d/m/Y') }}
        @if (
            $row['latest_due_at']
            && ! $row['latest_due_at']->isSameDay(
                $row['oldest_due_at'],
            )
        )
            → {{ $row['latest_due_at']->format('d/m/Y') }}
        @endif

        @if ($row['has_today'])
            · hoy también existe una nueva ocurrencia
        @endif
    </div>

    <div class="actions">
        <a
            class="action"
            href="{{ route(
                'daily-ops.show',
                array_filter([
                    'view' => $selectedWorkView,
                    'scope' => $selectedScope,
                    'recurring_rule' => $ruleId,
                ]),
            ) }}"
        >
            Ver pendientes
        </a>

        @if ($canWriteTask)
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
                    value="complete"
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

                @if ($selectedRecurringRule)
                    <input
                        type="hidden"
                        name="recurring_rule"
                        value="{{ $selectedRecurringRule }}"
                    >
                @endif

                <button
                    class="action done"
                    type="submit"
                    data-busy-label="Completando…"
                >
                    ✓ Completar más antigua
                </button>
            </form>
        @endif
    </div>

    <div class="secondary-links">
        <a
            href="{{ route(
                'recurring-task-front.edit',
                $ruleId,
            ) }}"
        >
            Administrar recurrencia →
        </a>
    </div>
</div>
