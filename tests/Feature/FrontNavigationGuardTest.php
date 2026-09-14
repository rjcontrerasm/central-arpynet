<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontNavigationGuardTest extends TestCase
{
    public function test_operational_front_surfaces_do_not_link_to_admin_resources(): void
    {
        $views = [
            'daily-ops.blade.php',
            'clients-ops.blade.php',
            'projects-ops.blade.php',
            'project-front-form.blade.php',
            'service-orders-ops.blade.php',
            'service-order-front-form.blade.php',
            'obligations-ops.blade.php',
            'recurring-obligations-front.blade.php',
            'recurring-obligation-front-form.blade.php',
            'recurring-tasks-front.blade.php',
            'recurring-task-front-form.blade.php',
            'incident-360.blade.php',
            'global-tracking.blade.php',
            'operational-360.blade.php',
            'operational-agenda.blade.php',
            'safety-recovery.blade.php',
        ];

        foreach ($views as $view) {
            $path = resource_path('views/'.$view);
            $contents = file_get_contents($path);

            $this->assertIsString($contents, $view);
            $this->assertStringNotContainsString(
                '/admin/',
                $contents,
                $view.' must stay on FRONT operational routes.',
            );
        }
    }

    public function test_advanced_admin_entry_is_the_only_explicit_admin_navigation(): void
    {
        $path = resource_path('views/components/operational-nav.blade.php');
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString("url('/admin')", $contents);
        $this->assertStringContainsString('Administración avanzada', $contents);
        $this->assertStringNotContainsString('/admin/', $contents);
    }
}
