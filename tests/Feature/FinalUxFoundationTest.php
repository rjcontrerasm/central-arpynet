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

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            if (
                ! is_string($contents)
                || ! str_contains($contents, '<x-operational-nav')
            ) {
                continue;
            }

            $checked++;

            $this->assertStringContainsString(
                '<x-operational-theme',
                $contents,
                basename($path).' must use the shared operational theme.',
            );
        }

        $this->assertGreaterThanOrEqual(
            12,
            $checked,
            'The final UX contract should cover the operational FRONT.',
        );
    }
}
