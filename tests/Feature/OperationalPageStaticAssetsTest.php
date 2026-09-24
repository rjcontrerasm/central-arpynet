<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationalPageStaticAssetsTest extends TestCase
{
    public function test_primary_operational_views_use_external_page_assets(): void
    {
        $pages = [
            'daily-ops' => false,
            'clients-ops' => true,
            'quick-capture' => true,
            'service-orders-ops' => false,
            'incident-360' => true,
            'global-search' => false,
            'recurring-task-front-form' => true,
            'recurring-obligation-front-form' => false,
            'operational-agenda' => false,
            'projects-ops' => false,
        ];

        foreach ($pages as $view => $hasJavascript) {
            $viewPath = resource_path(
                'views/'.$view.'.blade.php',
            );
            $contents = file_get_contents($viewPath);

            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                '<style',
                $contents,
                $view,
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<script>.*<\/script>/s',
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

            if (! $hasJavascript) {
                continue;
            }

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
    }
}
