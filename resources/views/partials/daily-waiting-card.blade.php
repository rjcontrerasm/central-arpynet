@php
    $followUpDue = $task->waiting_until
        && $task->waiting_until->lte(
            $now->toDateString(),
        );

    $canWriteTask = $currentUser
        ?->canWriteToOrganization(
            (int) $task->organization_id,
        ) ?? false;
@endphp

<div
    class="item daily-waiting-card daily-waiting-live"
    data-operational-card
    data-daily-waiting-card
    data-daily-task-id="{{ $task->id }}"
>
    <div class="item-title">
        {{ $task->title }}
    </div>

    <div class="meta">
        {{ $task->organization?->name
            ?? 'Sin ámbito' }}

        @if ($task->waiting_reason)
            · {{ $task->waiting_reason }}
        @endif

        @if ($selectedWorkView === 'team')
            · Responsable:
            {{ $task->assignee?->name
                ?? 'Sin asignar' }}
        @endif
    </div>

    @if ($task->waiting_until)
        <div class="meta {{
            $followUpDue
                ? 'waiting-due'
                : ''
        }}">
            Seguimiento:
            {{ $task->waiting_until->format(
                'd/m/Y',
            ) }}
        </div>
    @endif

    @if ($canWriteTask)
        <form
            class="resume-form"
            method="POST"
            action="{{ route(
                'daily-task-waiting.resume',
                $task,
            ) }}"
        >
            @csrf
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

            @if ($selectedWorkTeam)
                <input
                    type="hidden"
                    name="work_team"
                    value="{{ $selectedWorkTeam }}"
                >
            @endif

            <button
                class="resume-button"
                type="submit"
                data-busy-label="Reactivando…"
            >
                Reactivar
            </button>
        </form>
    @endif
</div>
