<div class="task-quick-row task-quick-row-waiting" data-daily-quick-row>
    <span class="task-quick-check task-quick-check-waiting" aria-hidden="true">⏸</span>

    <span class="task-quick-title task-quick-title-readonly">
        {{ $task->title }}
    </span>

    <span
        class="task-organization-badge task-quick-organization organization-tone-{{ $organizationTone }}"
        title="{{ $task->organization?->name ?? 'Sin ámbito' }}"
    >
        <span class="task-organization-dot" aria-hidden="true"></span>
        {{ $task->organization?->name ?? 'Sin ámbito' }}
    </span>

    <span class="task-quick-date {{ $followUpDue ? 'is-overdue' : '' }}">
        @if ($task->waiting_until)
            {{ $task->waiting_until->format('d/m/Y') }}
        @else
            Sin seguimiento
        @endif
    </span>

    <span class="task-quick-status waiting">
        En espera
    </span>

    @if ($canWriteTask)
        <form
            class="task-quick-resume"
            method="POST"
            action="{{ route('daily-task-waiting.resume', $task) }}"
        >
            @csrf
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
            @if ($selectedWorkTeam)
                <input type="hidden" name="work_team" value="{{ $selectedWorkTeam }}">
            @endif

            <button type="submit" data-busy-label="Reactivando…">
                Reactivar
            </button>
        </form>
    @endif
</div>
