<div class="task-quick-row task-quick-row-recurring" data-daily-quick-row>
    <span class="task-quick-check task-quick-check-recurring" aria-hidden="true">↻</span>

    <a
        class="task-quick-title"
        href="{{ route(
            'daily-ops.show',
            array_filter([
                'view' => $selectedWorkView,
                'scope' => $selectedScope,
                'priority' => 'overdue',
                'recurring_rule' => $ruleId,
            ]),
        ) }}"
    >
        {{ $task->title }}
    </a>

    <span
        class="task-organization-badge task-quick-organization organization-tone-{{ $organizationTone }}"
        title="{{ $task->organization?->name ?? 'Sin ámbito' }}"
    >
        <span class="task-organization-dot" aria-hidden="true"></span>
        {{ $task->organization?->name ?? 'Sin ámbito' }}
    </span>

    <span class="task-quick-date is-overdue">
        {{ $row['oldest_due_at']?->format('d/m/Y') ?? 'Sin fecha' }}
    </span>

    <span class="task-quick-status is-overdue">
        Vencidas × {{ $row['count'] }}
    </span>

    @if ($canWriteTask)
        <form
            class="task-quick-complete action-form"
            method="POST"
            action="{{ route('daily-task-action.update', $task) }}"
        >
            @csrf
            <input type="hidden" name="action" value="complete">
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
            @if ($selectedRecurringRule)
                <input type="hidden" name="recurring_rule" value="{{ $selectedRecurringRule }}">
            @endif

            <button type="submit" class="task-quick-secondary" data-busy-label="…">
                ✓ Antigua
            </button>
        </form>
    @endif
</div>
