<style>
    :root {
        font-family:
            Inter,
            ui-sans-serif,
            system-ui,
            -apple-system,
            BlinkMacSystemFont,
            "Segoe UI",
            sans-serif;

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

        /*
         * Compatibilidad con las primeras vistas operativas. Varias pantallas
         * (Jarvis, Automatizaciones, Vista 360, etc.) consumen todavía los
         * tokens --op-*. Sin este puente caían a sus fallbacks oscuros aun
         * cuando el shell estaba en tema claro.
         */
        --op-bg: var(--central-bg);
        --op-card: var(--central-surface);
        --op-card-soft: var(--central-surface-soft);
        --op-border: var(--central-border);
        --op-border-strong: var(--central-border-strong);
        --op-text: var(--central-text);
        --op-muted: var(--central-muted);
        --op-primary: var(--central-primary);
        --op-primary-soft: var(--central-primary-soft);
    }

    body {
        background: var(--central-bg) !important;
        color: var(--central-text) !important;
    }

    /*
     * 2.36.3 — FRONT link consistency contract.
     *
     * Las vistas operativas no deben caer al estilo nativo del navegador
     * (azul/morado y subrayado) cuando un enlace no declara una variante
     * propia. :where() mantiene especificidad cero para que clases como
     * .section-link, .admin-link o los enlaces de navegación puedan conservar
     * sus colores y tratamientos deliberados.
     */
    :where(a:any-link) {
        color: inherit;
        text-decoration: none;
    }

    :where(a:any-link:hover) {
        text-decoration: none;
    }

    :where(a:any-link:focus-visible) {
        outline: 2px solid var(--central-primary);
        outline-offset: 2px;
        border-radius: 4px;
    }

    button,
    input,
    select,
    textarea {
        font: inherit;
    }

    .brand {
        color: var(--central-text);
    }

    .operational-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 8px;
        min-width: 0;
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
    textarea,
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

    /*
     * 2.36.2 — Jarvis readability contract.
     *
     * Jarvis concentra mucha información operativa en una sola superficie.
     * La primera versión utilizaba varios textos auxiliares entre 8 y 10 px,
     * que resultaban demasiado densos en escritorio. Estos overrides están
     * deliberadamente acotados a .jarvis para mejorar lectura sin alterar la
     * geometría de las demás pantallas FRONT.
     */
    .jarvis .safety span,
    .jarvis .metric span {
        font-size: 11px !important;
        line-height: 1.4 !important;
    }

    .jarvis .executive-org strong,
    .jarvis .executive-title,
    .jarvis .daily-section h3,
    .jarvis .daily-title,
    .jarvis .review-column h3,
    .jarvis .review-item-title,
    .jarvis .priority-name,
    .jarvis .attention-title {
        font-size: 12px !important;
        line-height: 1.4 !important;
    }

    .jarvis .executive-org-meta,
    .jarvis .executive-reason,
    .jarvis .daily-section-hint,
    .jarvis .daily-reason,
    .jarvis .daily-move,
    .jarvis .review-item-meta,
    .jarvis .priority-why,
    .jarvis .priority-move,
    .jarvis .attention-meta,
    .jarvis .prepare-note {
        font-size: 10.5px !important;
        line-height: 1.55 !important;
    }

    .jarvis .executive-scope,
    .jarvis .daily-scope,
    .jarvis .review-item-scope,
    .jarvis .proposal-compatible,
    .jarvis .proposal-field label,
    .jarvis .driver,
    .jarvis .daily-focus span,
    .jarvis .review-step,
    .jarvis .context-count span {
        font-size: 10px !important;
        line-height: 1.4 !important;
    }

    .jarvis .priority-rank,
    .jarvis .quick-link,
    .jarvis .review-link,
    .jarvis .prepare-button,
    .jarvis .badge,
    .jarvis .button {
        font-size: 11px !important;
        line-height: 1.35 !important;
    }

    .jarvis .proposal-input,
    .jarvis .meta,
    .jarvis .guard,
    .jarvis .empty {
        font-size: 12px !important;
        line-height: 1.5 !important;
    }

    .jarvis .daily-item,
    .jarvis .review-item {
        padding: 10px !important;
    }

    .jarvis .daily-items,
    .jarvis .review-items {
        gap: 9px !important;
    }

    .jarvis .daily-plan-grid,
    .jarvis .review-grid {
        gap: 10px !important;
    }

    .operational-focus-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 18px;
        padding: 14px 16px;
        border: 1px solid var(--central-border);
        border-left-width: 4px;
        border-radius: var(--central-radius);
        background: var(--central-surface);
        box-shadow: var(--central-shadow);
    }

    .operational-focus-banner.is-danger {
        border-left-color: var(--central-danger);
    }

    .operational-focus-banner.is-warning {
        border-left-color: var(--central-warning);
    }

    .operational-focus-banner.is-success {
        border-left-color: var(--central-success);
    }

    .operational-focus-banner.is-info {
        border-left-color: var(--central-info);
    }

    .operational-focus-eyebrow {
        color: var(--central-muted);
        font-size: 10px;
        font-weight: 850;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .operational-focus-title {
        margin-top: 3px;
        color: var(--central-text);
        font-size: 16px;
        font-weight: 850;
        line-height: 1.3;
    }

    .operational-focus-meta {
        margin-top: 4px;
        color: var(--central-muted);
        font-size: 11px;
        line-height: 1.45;
    }

    .operational-focus-actions {
        display: flex;
        flex: 0 0 auto;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .operational-context-heading {
        margin: 24px 0 8px;
        color: var(--central-muted);
        font-size: 11px;
        font-weight: 820;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    @media (max-width: 620px) {
        .operational-focus-banner {
            align-items: flex-start;
            flex-direction: column;
        }

        .operational-focus-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .operational-header-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .jarvis .executive-org-meta,
        .jarvis .executive-reason,
        .jarvis .daily-section-hint,
        .jarvis .daily-reason,
        .jarvis .daily-move,
        .jarvis .review-item-meta,
        .jarvis .priority-why,
        .jarvis .priority-move,
        .jarvis .attention-meta,
        .jarvis .prepare-note {
            font-size: 11px !important;
        }

        .jarvis .daily-title,
        .jarvis .review-item-title,
        .jarvis .priority-name,
        .jarvis .attention-title {
            font-size: 12.5px !important;
        }
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

<x-operational-interactions />