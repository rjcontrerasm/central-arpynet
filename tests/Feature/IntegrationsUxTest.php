<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationsUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrations_page_explains_operational_state(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        config()->set(
            'whatsapp.enabled',
            true,
        );

        config()->set(
            'whatsapp.outbound_enabled',
            false,
        );

        config()->set(
            'whatsapp.access_token',
            null,
        );

        config()->set(
            'whatsapp.phone_number_id',
            'configured-test-id',
        );

        config()->set(
            'whatsapp.allowed_wa_ids',
            ['51999999999'],
        );

        config()->set(
            'central.summary_whatsapp.enabled',
            false,
        );

        config()->set(
            'central.summary_whatsapp.template',
            'central_executive_summary',
        );

        config()->set(
            'central.summary_whatsapp.language',
            'es_PE',
        );

        config()->set(
            'central.critical_whatsapp.enabled',
            false,
        );

        $this->actingAs($user)
            ->get('/admin/integraciones')
            ->assertOk()
            ->assertSee('Estado de integraciones')
            ->assertSee('Recepción activa')
            ->assertSee('Pendiente de Meta')
            ->assertSee('Preparado en Central')
            ->assertSee('central_executive_summary')
            ->assertSee('WhatsApp saliente permanece apagado');
    }

    public function test_integrations_page_never_renders_secret_values(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        config()->set(
            'whatsapp.verify_token',
            'never-render-this-verify-token',
        );

        config()->set(
            'whatsapp.app_secret',
            'never-render-this-app-secret',
        );

        config()->set(
            'whatsapp.access_token',
            'never-render-this-access-token',
        );

        $response = $this->actingAs($user)
            ->get('/admin/integraciones')
            ->assertOk();

        $response
            ->assertDontSee(
                'never-render-this-verify-token',
            )
            ->assertDontSee(
                'never-render-this-app-secret',
            )
            ->assertDontSee(
                'never-render-this-access-token',
            );
    }
}
