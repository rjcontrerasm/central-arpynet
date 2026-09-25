<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationalFrontAssetsCompletionTest extends TestCase
{
    public function test_remaining_operational_views_use_external_css(): void
    {
        foreach ([
            'agent-proposals',
            'executive-summary',
            'decision-inbox',
            'safety-recovery',
            'global-tracking',
            'obligations-ops',
            'audit-history',
            'weekly-review',
            'automation-center',
            'service-order-front-form',
            'project-front-form',
            'operational-360',
            'daily-review',
            'central-copilot',
            'task-convert',
            'notification-center',
            'task-trash',
            'recurring-obligations-front',
            'recurring-tasks-front',
        ] as $view) {
            $contents = file_get_contents(
                resource_path(
                    "views/{$view}.blade.php",
                ),
            );

            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                '<style',
                $contents,
                $view,
            );
            $this->assertStringContainsString(
                "central-assets/pages/{$view}.css",
                $contents,
                $view,
            );
            $this->assertFileExists(
                public_path(
                    "central-assets/pages/{$view}.css",
                ),
            );
        }
    }

    public function test_front_blade_views_are_compatible_with_strict_script_and_style_csp(): void
    {
        $paths = array_merge(
            glob(resource_path('views/*.blade.php')) ?: [],
            glob(resource_path('views/components/*.blade.php')) ?: [],
            glob(resource_path('views/partials/*.blade.php')) ?: [],
            glob(resource_path('views/collaboration/*.blade.php')) ?: [],
        );

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                '<style',
                $contents,
                $path,
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\\sstyle\\s*=\\s*["\\\']/i',
                $contents,
                $path,
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<script(?![^>]*\\bsrc\\s*=)[^>]*>/i',
                $contents,
                $path,
            );
        }
    }

    public function test_operational_scripts_are_externalized(): void
    {
        foreach ([
            'service-order-front-form',
            'notification-center',
            'task-convert',
        ] as $view) {
            $contents = file_get_contents(
                resource_path(
                    "views/{$view}.blade.php",
                ),
            );

            $this->assertIsString($contents);
            $this->assertDoesNotMatchRegularExpression(
                '/<script>.*<\/script>/s',
                $contents,
                $view,
            );
            $this->assertStringContainsString(
                "central-assets/pages/{$view}.js",
                $contents,
                $view,
            );
            $this->assertFileExists(
                public_path(
                    "central-assets/pages/{$view}.js",
                ),
            );
        }

        $taskConvert = file_get_contents(
            resource_path(
                'views/task-convert.blade.php',
            ),
        );

        $this->assertIsString($taskConvert);
        $this->assertStringContainsString(
            'data-suggested-anchors',
            $taskConvert,
        );
        $this->assertStringContainsString(
            "filemtime(public_path('central-assets/pages/task-convert.js'))",
            $taskConvert,
        );
    }
}
