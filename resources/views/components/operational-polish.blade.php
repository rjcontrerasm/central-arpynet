<style>
    :root {
        --central-tap-target: 40px;
        --central-mobile-tap-target: 44px;
        --central-safe-gutter: 12px;
    }

    html {
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }

    body {
        min-width: 0;
        overflow-x: hidden;
    }

    img,
    svg,
    video,
    canvas {
        max-width: 100%;
    }

    button,
    input,
    select,
    textarea {
        font: inherit;
    }

    input,
    select,
    textarea {
        max-width: 100%;
    }

    textarea {
        resize: vertical;
    }

    :where(
        .item-title,
        .title,
        .card-title,
        .meta,
        .summary-detail,
        .safety-row-meta
    ) {
        overflow-wrap: anywhere;
    }

    .op-nav-menu {
        max-height: min(68vh, 560px);
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
    }

    .op-nav-menu a {
        min-height: var(--central-mobile-tap-target);
    }

    .global-undo-bar {
        bottom: max(18px, env(safe-area-inset-bottom));
    }

    .global-undo-button {
        min-height: var(--central-tap-target);
    }

    @media (max-width: 759px) {
        :root {
            --central-tap-target: var(--central-mobile-tap-target);
        }

        .topbar {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr);
            align-items: start !important;
            gap: 10px !important;
        }

        .topbar > .op-nav {
            width: 100%;
            min-width: 0;
            justify-content: flex-start;
            overflow-x: auto;
            overscroll-behavior-x: contain;
            scrollbar-width: none;
            padding-bottom: 2px;
        }

        .topbar > .op-nav::-webkit-scrollbar {
            display: none;
        }

        .op-nav-link,
        .op-nav-more > summary {
            min-height: var(--central-mobile-tap-target) !important;
            padding-inline: 8px;
            font-size: 12px;
        }

        .op-nav-more > summary {
            max-width: min(42vw, 170px);
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .op-nav-menu {
            position: fixed;
            z-index: 310;
            top: auto;
            right: max(
                var(--central-safe-gutter),
                env(safe-area-inset-right)
            );
            bottom: max(
                var(--central-safe-gutter),
                env(safe-area-inset-bottom)
            );
            left: max(
                var(--central-safe-gutter),
                env(safe-area-inset-left)
            );
            width: auto;
            max-height: calc(100dvh - 96px);
            padding: 10px;
            border-radius: 18px;
        }

        .op-nav-menu a {
            min-height: var(--central-mobile-tap-target);
            padding: 10px 11px;
        }

        .global-undo-bar {
            right: max(
                var(--central-safe-gutter),
                env(safe-area-inset-right)
            );
            bottom: max(
                var(--central-safe-gutter),
                env(safe-area-inset-bottom)
            );
            left: max(
                var(--central-safe-gutter),
                env(safe-area-inset-left)
            );
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            max-width: none;
            gap: 10px;
        }

        .global-undo-button {
            min-height: var(--central-mobile-tap-target);
        }

        input,
        select,
        textarea {
            font-size: 16px !important;
        }

        .section-head {
            min-width: 0;
            flex-wrap: wrap;
        }
    }

    @media (max-width: 420px) {
        .global-undo-bar {
            grid-template-columns: minmax(0, 1fr);
        }

        .global-undo-button {
            width: 100%;
        }
    }

    @media (forced-colors: active) {
        .op-nav-link.is-active,
        .op-nav-menu a.is-active,
        .global-undo-button {
            outline: 2px solid CanvasText;
            outline-offset: 2px;
        }
    }
</style>
