<?php

namespace Tests\Feature;

use Tests\TestCase;

class FinalUxFoundationTest extends TestCase
{
    public function test_operational_polish_has_mobile_and_accessibility_contract(): void
    {
        $path = public_path(
            'central-assets/operational.css',
        );
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString(
            '--central-mobile-tap-target: 44px',
            $contents,
        );
        $this->assertStringContainsString(
            'max-height: calc(100dvh - 96px)',
            $contents,
        );
        $this->assertStringContainsString(
            'env(safe-area-inset-bottom)',
            $contents,
        );
        $this->assertStringContainsString(
            'font-size: 16px !important',
            $contents,
        );
        $this->assertStringContainsString(
            '@media (forced-colors: active)',
            $contents,
        );
    }

    public function test_shared_operational_theme_owns_typography_contract(): void
    {
        $path = public_path(
            'central-assets/operational.css',
        );
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString('font-family:', $contents);
        $this->assertStringContainsString('Inter,', $contents);
        $this->assertStringContainsString('ui-sans-serif,', $contents);
        $this->assertStringContainsString('system-ui,', $contents);
        $this->assertStringContainsString('"Segoe UI",', $contents);
        $this->assertStringContainsString('button,', $contents);
        $this->assertStringContainsString('textarea {', $contents);
        $this->assertStringContainsString('font: inherit;', $contents);
    }

    public function test_shared_operational_theme_guards_front_link_consistency_contract(): void
    {
        $path = public_path(
            'central-assets/operational.css',
        );
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString(
            '2.36.3 — FRONT link consistency contract',
            $contents,
        );
        $this->assertStringContainsString(
            ':where(a:any-link) {',
            $contents,
        );
        $this->assertStringContainsString(
            'color: inherit;',
            $contents,
        );
        $this->assertStringContainsString(
            'text-decoration: none;',
            $contents,
        );
        $this->assertStringContainsString(
            ':where(a:any-link:focus-visible) {',
            $contents,
        );
        $this->assertStringContainsString(
            'outline: 2px solid var(--central-primary);',
            $contents,
        );
    }

    public function test_shared_operational_theme_guards_jarvis_readability_contract(): void
    {
        $path = public_path(
            'central-assets/operational.css',
        );
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString(
            '2.36.2 — Jarvis readability contract',
            $contents,
        );
        $this->assertStringContainsString(
            '.jarvis .executive-org-meta',
            $contents,
        );
        $this->assertStringContainsString(
            '.jarvis .daily-title',
            $contents,
        );
        $this->assertStringContainsString(
            '.jarvis .review-item-meta',
            $contents,
        );
        $this->assertStringContainsString(
            '.jarvis .priority-why',
            $contents,
        );
        $this->assertStringContainsString(
            'font-size: 10.5px !important',
            $contents,
        );
        $this->assertStringContainsString(
            'font-size: 12px !important',
            $contents,
        );
        $this->assertStringContainsString(
            '@media (max-width: 620px)',
            $contents,
        );
    }

    public function test_shared_interactions_cover_keyboard_motion_and_bfcache(): void
    {
        $css = file_get_contents(
            public_path(
                'central-assets/operational.css',
            ),
        );
        $js = file_get_contents(
            public_path(
                'central-assets/operational.js',
            ),
        );
        $loader = file_get_contents(
            resource_path(
                'views/components/operational-assets.blade.php',
            ),
        );

        $this->assertIsString($css);
        $this->assertIsString($js);
        $this->assertIsString($loader);

        $this->assertStringContainsString('@once', $loader);
        $this->assertStringContainsString(
            'central-assets/operational.css',
            $loader,
        );
        $this->assertStringContainsString(
            'central-assets/operational.js',
            $loader,
        );
        $this->assertStringContainsString('textarea', $css);
        $this->assertStringContainsString(
            'prefers-reduced-motion: reduce',
            $css,
        );
        $this->assertStringContainsString(
            "'DOMContentLoaded'",
            $js,
        );
        $this->assertStringContainsString(
            '__centralOperationalInteractionsInstalled',
            $js,
        );
        $this->assertStringContainsString(
            "'pageshow'",
            $js,
        );
        $this->assertStringContainsString(
            'data-original-html',
            $js,
        );
        $this->assertStringContainsString(
            "querySelector('summary')?.focus()",
            $js,
        );
    }

    public function test_operational_navigation_exposes_recurring_workflows(): void
    {
        $path = resource_path(
            'views/components/operational-nav.blade.php',
        );
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString(
            "request()->routeIs('recurring-task-front.*')",
            $contents,
        );
        $this->assertStringContainsString(
            "request()->routeIs('recurring-obligation-front.*')",
            $contents,
        );
        $this->assertStringContainsString(
            "'recurring-tasks' => 'Tareas recurrentes'",
            $contents,
        );
        $this->assertStringContainsString(
            "'recurring-obligations' => 'Vencimientos recurrentes'",
            $contents,
        );
        $this->assertStringContainsString(
            "route('recurring-task-front.index')",
            $contents,
        );
        $this->assertStringContainsString(
            "route('recurring-obligation-front.index')",
            $contents,
        );
    }

    public function test_operational_front_views_with_navigation_inherit_shared_ux_contract(): void
    {
        $themePath = resource_path(
            'views/components/operational-theme.blade.php',
        );
        $theme = file_get_contents($themePath);

        $this->assertIsString($theme);
        $this->assertStringContainsString(
            '<x-operational-assets />',
            $theme,
            'The operational theme must load the shared static assets.',
        );

        $paths = glob(resource_path('views/*.blade.php')) ?: [];
        $checked = 0;
        $missingTheme = [];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            if (
                ! is_string($contents)
                || ! str_contains($contents, '<x-operational-nav')
            ) {
                continue;
            }

            $checked++;

            if (! str_contains($contents, '<x-operational-theme')) {
                $missingTheme[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $missingTheme,
            'Operational FRONT views missing the shared theme: '
                .implode(', ', $missingTheme),
        );

        $this->assertGreaterThanOrEqual(
            12,
            $checked,
            'The final UX contract should cover the operational FRONT.',
        );
    }
}
