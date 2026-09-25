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

    public function test_static_scripts_are_externalized_but_task_conversion_keeps_dynamic_script_explicit(): void
    {
        foreach ([
            'service-order-front-form',
            'notification-center',
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
        $this->assertMatchesRegularExpression(
            '/<script>.*<\/script>/s',
            $taskConvert,
            'La conversión mantiene temporalmente JS dinámico dependiente de Blade.',
        );
    }
}
