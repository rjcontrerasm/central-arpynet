@php
    $urgencyOptions = [
        'low' => 'Baja',
        'normal' => 'Normal',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ];

    $impactOptions = [
        'low' => 'Bajo',
        'normal' => 'Normal',
        'high' => 'Alto',
        'critical' => 'Crítico',
    ];
@endphp

<div class="task-quick-row" data-daily-quick-row>
    @if ($canWriteTask)
        <form
            class="task-quick-complete action-form"
            data-daily-action="complete"
            data-has-next-action="{{ filled($task->next_action) ? '1' : '0' }}"
            method="POST"
            action="{{ route('daily-task-action.update', $task) }}"
        >
            @csrf
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="follow_up_mode" value="none" data-follow-up-mode>
            <input type="hidden" name="follow_up_due_date" value="" data-follow-up-due-date>
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

            <button
                class="task-quick-check"
                type="submit"
                data-busy-label="…"
                aria-label="Marcar como hecha: {{ $task->title }}"
                title="Marcar como hecha"
            >
                ✓
            </button>
        </form>
    @else
        <span class="task-quick-check task-quick-check-readonly" aria-hidden="true">•</span>
    @endif

    @if ($canWriteTask)
        <button
            class="task-quick-title"
            type="button"
            data-quick-open-edit="task-edit-dialog-{{ $task->id }}"
            title="Abrir edición"
        >
            {{ $task->title }}
        </button>
    @else
        <span class="task-quick-title task-quick-title-readonly">
            {{ $task->title }}
        </span>
    @endif

    <span
        class="task-organization-badge task-quick-organization organization-tone-{{ $organizationTone }}"
        title="{{ $task->organization?->name ?? 'Sin ámbito' }}"
    >
        <span class="task-organization-dot" aria-hidden="true"></span>
        {{ $task->organization?->name ?? 'Sin ámbito' }}
    </span>

    <span class="task-quick-date {{ $isOverdue ? 'is-overdue' : '' }}">
        {{ $task->due_at ? $task->due_at->format('d/m/Y') : 'Sin fecha' }}
    </span>

    <span class="task-quick-status {{ $isOverdue ? 'is-overdue' : $band }}">
        {{ $isOverdue ? 'Vencida' : $task->display_priority_label }}
    </span>

    @if ($canWriteTask)
        <details class="task-quick-priority">
            <summary>
                Prioridad
                <span aria-hidden="true">⌄</span>
            </summary>

            <form
                class="task-quick-priority-form"
                method="POST"
                action="{{ route('daily-task-priority.update', $task) }}"
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

                <label>
                    Urgencia
                    <select name="urgency">
                        @foreach ($urgencyOptions as $value => $label)
                            <option value="{{ $value }}" @selected($task->urgency === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Impacto
                    <select name="impact">
                        @foreach ($impactOptions as $value => $label)
                            <option value="{{ $value }}" @selected($task->impact === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" data-busy-label="Guardando…">
                    Aplicar
                </button>
            </form>
        </details>
    @else
        <span class="task-quick-priority-readonly">
            {{ ucfirst($task->urgency) }} / {{ ucfirst($task->impact) }}
        </span>
    @endif
</div>
