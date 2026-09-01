<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_page_is_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/servicios')
            ->assertOk()
            ->assertSee('Servicios')
            ->assertSee('Órdenes, siguiente acción y estancamiento');
    }

    public function test_overdue_next_action_is_critical(): void
    {
        [$user, $organization, $client] =
            $this->context();

        $order = $this->order(
            $user,
            $organization,
            $client,
            [
                'title' => 'Servicio crítico',
                'stage' => 'execution',
                'next_action' => 'Enviar informe',
                'next_action_at' => now()->subDay(),
            ],
        );

        $this->actingAs($user)
            ->get('/servicios')
            ->assertOk()
            ->assertSee('Servicio crítico')
            ->assertSee('Siguiente acción vencida')
            ->assertSee('Crítica');
    }

    public function test_foreign_scope_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'ajena',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/servicios?scope='.$foreign->id)
            ->assertForbidden();
    }

    public function test_quick_update_changes_stage_and_next_action(): void
    {
        [$user, $organization, $client] =
            $this->context();

        $order = $this->order(
            $user,
            $organization,
            $client,
            [
                'stage' => 'order_received',
            ],
        );

        $this->actingAs($user)
            ->post(
                "/servicios/{$order->id}/actualizar",
                [
                    'stage' => 'execution',
                    'next_action' => 'Presentar avance',
                    'next_action_at' =>
                        '2026-09-05 10:00:00',
                    'focus' => 'all',
                ],
            )
            ->assertRedirect('/servicios?focus=all');

        $order->refresh();

        $this->assertSame(
            'execution',
            $order->stage,
        );

        $this->assertSame(
            'Presentar avance',
            $order->next_action,
        );

        $this->assertNotNull(
            $order->last_activity_at,
        );
    }

    private function order(
        User $user,
        Organization $organization,
        Client $client,
        array $overrides = [],
    ): ServiceOrder {
        return ServiceOrder::query()->create(
            array_merge(
                [
                    'organization_id' =>
                        $organization->id,
                    'client_id' => $client->id,
                    'title' => 'Servicio de prueba',
                    'stage' => 'opportunity',
                    'currency' => 'PEN',
                    'includes_tax' => true,
                    'created_by' => $user->id,
                ],
                $overrides,
            ),
        );
    }

    private function context(): array
    {
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

        $organization->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente prueba',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return [
            $user,
            $organization,
            $client,
        ];
    }
}
