<style>
    :root {
        --central-bg: #f2f5f9;
        --central-surface: #ffffff;
        --central-surface-soft: #f7f9fc;
        --central-border: #d2dde9;
        --central-border-strong: #bdcad9;
        --central-text: #10213a;
        --central-muted: #5e6f85;

        --central-primary: #245fd7;
        --central-primary-hover: #1d4fb8;
        --central-primary-soft: #eaf2ff;
        --central-primary-text: #1d4fa7;

        --central-danger: #b93b36;
        --central-danger-soft: #fff1f0;

        --central-warning: #9a6509;
        --central-warning-soft: #fff8e6;

        --central-attention: #b45309;
        --central-attention-soft: #fff4e8;

        --central-success: #18794e;
        --central-success-soft: #eefaf3;

        --central-shadow:
            0 7px 20px rgba(15, 23, 42, .045);
    }

    body {
        background: var(--central-bg) !important;
        color: var(--central-text) !important;
    }

    .brand {
        color: var(--central-text);
    }

    .subtitle,
    .date,
    .meta,
    .stat-label,
    .money-label,
    .recent-meta,
    .empty,
    .field,
    .edit-field,
    .summary-detail {
        color: var(--central-muted) !important;
    }

    .stat,
    .item,
    .card,
    .money,
    .notification,
    .recent-item {
        border-color: var(--central-border) !important;
        background: var(--central-surface) !important;
    }

    .item,
    .card,
    .notification,
    .recent-item {
        box-shadow:
            0 1px 2px rgba(15, 23, 42, .025),
            var(--central-shadow);
    }

    .scope,
    .priority-filter,
    .chip,
    .action,
    .task-edit summary,
    details > summary,
    .small-action,
    .read-all {
        border-color: var(--central-border-strong) !important;
        background: var(--central-surface) !important;
        color: #495b72 !important;
    }

    .scope.active,
    .priority-filter.active,
    .chip.active {
        border-color: #7eb0ff !important;
        background: var(--central-primary-soft) !important;
        color: var(--central-primary-text) !important;
    }

    input,
    select,
    .search-input,
    .search input,
    .waiting-form input,
    .edit-field input,
    .edit-field select,
    .payment-form input,
    .editor input,
    .editor select {
        border-color: var(--central-border-strong) !important;
        background: var(--central-surface) !important;
        color: var(--central-text) !important;
    }

    .search-button,
    .search button,
    .quick,
    .fab,
    .submit,
    .admin-link,
    .save,
    .save-edit,
    .pay-button {
        background: var(--central-primary) !important;
        color: #fff !important;
    }

    .search-button:hover,
    .search button:hover,
    .quick:hover,
    .fab:hover,
    .submit:hover,
    .admin-link:hover,
    .save:hover,
    .save-edit:hover,
    .pay-button:hover {
        background: var(--central-primary-hover) !important;
    }

    .pill {
        background: #f1f5f9 !important;
        color: #526277 !important;
    }

    .pill.critical,
    .pill.overdue,
    .pill.today.critical {
        background: var(--central-danger-soft) !important;
        color: var(--central-danger) !important;
    }

    .pill.attention {
        background: var(--central-attention-soft) !important;
        color: var(--central-attention) !important;
    }

    .pill.watch,
    .pill.week,
    .pill.upcoming {
        background: var(--central-warning-soft) !important;
        color: var(--central-warning) !important;
    }

    .pill.today,
    .pill.receivable {
        background: var(--central-primary-soft) !important;
        color: var(--central-primary-text) !important;
    }

    .pill.paid,
    .action.done,
    .success {
        background: var(--central-success-soft) !important;
        color: var(--central-success) !important;
        border-color: #a9dec0 !important;
    }

    .reason,
    .waiting-due {
        color: var(--central-warning) !important;
    }

    .section-link,
    .clear-filter,
    .go,
    .money-title {
        color: #4f83d9 !important;
    }

    .empty {
        border-color: var(--central-border-strong) !important;
        background: rgba(255, 255, 255, .58);
    }

    .danger-value {
        color: #d85a55 !important;
    }

    .today-value {
        color: #4f8fe6 !important;
    }

    .next,
    .editor,
    .edit-form,
    .waiting-form,
    .amount,
    .payment-form {
        background: var(--central-surface-soft) !important;
    }

    @media (hover: hover) and (pointer: fine) {
        .stat:hover,
        .item:hover,
        .card:hover,
        .notification:hover,
        .recent-item:hover {
            border-color: #b7c7da !important;
        }
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --central-bg: #0b1220;
            --central-surface: #111b2e;
            --central-surface-soft: #0e1728;
            --central-border: #25344c;
            --central-border-strong: #35465f;
            --central-text: #f1f5f9;
            --central-muted: #9aa8bc;

            --central-primary: #3b82f6;
            --central-primary-hover: #60a5fa;
            --central-primary-soft: #172554;
            --central-primary-text: #bfdbfe;

            --central-danger: #fecaca;
            --central-danger-soft: #451a1a;

            --central-warning: #fde68a;
            --central-warning-soft: #422006;

            --central-attention: #fed7aa;
            --central-attention-soft: #431407;

            --central-success: #bbf7d0;
            --central-success-soft: #052e16;
        }

        .scope,
        .priority-filter,
        .chip,
        .action,
        .task-edit summary,
        details > summary,
        .small-action,
        .read-all {
            color: #cbd5e1 !important;
        }

        .pill {
            background: #1e293b !important;
            color: #cbd5e1 !important;
        }

        .empty {
            background: rgba(15, 23, 42, .35);
        }
    }
</style>
