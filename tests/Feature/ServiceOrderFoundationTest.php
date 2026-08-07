<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_service_order_pipeline_and_attention(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente de prueba',
            'is_active' => true,
        ]);

        $order = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio de prueba',
            'stage' => 'quotation',
            'next_action' => 'Solicitar respuesta',
            'next_action_at' => now()->subDay(),
            'amount' => 1000,
            'currency' => 'PEN',
        ]);

        $this->assertSame(
            'Seguimiento vencido',
            $order->attention_label,
        );

        $this->assertSame(
            $user->id,
            $order->created_by,
        );
    }

    public function test_overdue_invoice_is_flagged(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $order = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio facturado',
            'stage' => 'invoiced',
            'invoice_due_date' => now()->subDay(),
        ]);

        $this->assertSame(
            'Cobranza vencida',
            $order->attention_label,
        );
    }
}
