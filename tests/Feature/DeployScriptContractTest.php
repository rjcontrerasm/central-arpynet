<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployScriptContractTest extends TestCase
{
    public function test_deploy_script_checks_routes_scheduler_and_health(): void
    {
        $script = file_get_contents(
            base_path('scripts/deploy-cpanel-central.sh'),
        );

        $this->assertIsString($script);
        $this->assertStringContainsString(
            'artisan route:list --path=mi-dia --except-vendor',
            $script,
        );
        $this->assertStringContainsString(
            'artisan schedule:list',
            $script,
        );
        $this->assertStringContainsString(
            '"$APP_URL/up"',
            $script,
        );
        $this->assertStringContainsString(
            '"$APP_URL/mi-dia"',
            $script,
        );
        $this->assertStringContainsString(
            'if [[ "$HEALTH_CODE" != "200" ]]',
            $script,
        );
    }
}
