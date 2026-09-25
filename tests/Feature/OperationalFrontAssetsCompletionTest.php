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

    public function test_front_views_do_not_require_inline_csp_exceptions(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                resource_path('views'),
            ),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()
                || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            $relative = str_replace(
                resource_path('views').DIRECTORY_SEPARATOR,
                '',
                $path,
            );

            if (str_starts_with($relative, 'filament'.DIRECTORY_SEPARATOR)
                || str_starts_with($relative, 'emails'.DIRECTORY_SEPARATOR)
                || $relative === 'welcome.blade.php') {
                continue;
            }

            $contents = file_get_contents($path);

            $this->assertIsString($contents);
            $this->assertDoesNotMatchRegularExpression(
                '/<style\\b/i',
                $contents,
                $relative,
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<script(?![^>]*\\bsrc=)[^>]*>/i',
                $contents,
                $relative,
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\\sstyle\\s*=/i',
                $contents,
                $relative,
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\\son[a-z]+\\s*=/i',
                $contents,
                $relative,
            );
            $this->assertStringNotContainsString(
                'javascript:',
                strtolower($contents),
                $relative,
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
