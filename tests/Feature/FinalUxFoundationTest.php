<?php

namespace Tests\Feature;

use Tests\TestCase;

class FinalUxFoundationTest extends TestCase
{
    public function test_operational_polish_has_mobile_and_accessibility_contract(): void
    {
        $path = resource_path(
            'views/components/operational-polish.blade.php',
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
        $path = resource_path(
            'views/components/operational-theme.blade.php',
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

    public function test_shared_operational_theme_guards_jarvis_readability_contract(): void
    {
        $path = resource_path(
            'views/components/operational-theme.blade.php',
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
        $path = resource_path(
            'views/components/operational-interactions.blade.php',
        );
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString('@once', $contents);
        $this->assertStringContainsString(
            '<x-operational-polish />',
            $contents,
        );
        $this->assertStringContainsString('textarea', $contents);
        $this->assertStringContainsString(
            'prefers-reduced-motion: reduce',
            $contents,
        );
        $this->assertStringContainsString(
            "'DOMContentLoaded'",
            $contents,
        );
        $this->assertStringContainsString(
            '__centralOperationalInteractionsInstalled',
            $contents,
        );
        $this->assertStringContainsString(
            "'pageshow'",
            $contents,
        );
        $this->assertStringContainsString(
            'data-original-html',
            $contents,
        );
        $this->assertStringContainsString(
            "querySelector('summary')?.focus()",
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
            '<x-operational-interactions />',
            $theme,
            'The operational theme must install the final shared interactions.',
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
