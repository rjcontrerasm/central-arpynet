<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WhatsappStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_never_prints_secret_values(): void
    {
        config()->set('whatsapp.enabled', true);
        config()->set('whatsapp.verify_token', 'private-verify-value');
        config()->set('whatsapp.app_secret', 'private-app-secret-value');
        config()->set('whatsapp.access_token', 'private-access-token-value');
        Artisan::call('whatsapp:status');
        $output = Artisan::output();
        $this->assertStringNotContainsString('private-verify-value', $output);
        $this->assertStringNotContainsString('private-app-secret-value', $output);
        $this->assertStringNotContainsString('private-access-token-value', $output);
        $this->assertStringContainsString('SECRET_VALUES_EXPOSED=NO', $output);
    }
}
