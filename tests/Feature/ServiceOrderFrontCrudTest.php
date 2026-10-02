<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderFrontCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_and_edit_service_in_front(): void
    {
        [$user, $organization] = $this->context('member');

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente Servicio FRONT',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $assignee = User::factory()->create([
            'email' => 'assignee-service-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($assignee->id, [
            'role' => 'member',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/servicios/nuevo')
            ->assertOk()
            ->assertSee('Nueva orden / servicio')
            ->assertSee('Nuevo cliente')
            ->assertDontSee('/admin/ordenes-servicio', false);

        $create = $this->actingAs($user)->post('/servicios', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to' => $assignee->id,
            'title' => 'Servicio FRONT',
            'description' => 'Servicio creado desde CENTRAL Front.',
            'stage' => 'quotation',
            'quotation_number' => 'COT-001',
            'quotation_date' => '2026-09-13',
            'order_number' => 'OS-001',
            'order_received_date' => '2026-09-14',
            'start_date' => '2026-09-15',
            'end_date' => '2026-10-15',
            'amount' => '1500.50',
            'invoice_amount' => '1500.50',
            'currency' => 'PEN',
            'includes_tax' => '1',
            'next_action' => 'Confirmar orden',
            'next_action_at' => '2026-09-16 10:00:00',
            'drive_url' => 'https://drive.google.com/example',
            'notes' => 'Operación FRONT',
        ]);

        $create->assertRedirect();

        $service = ServiceOrder::query()
            ->where('title', 'Servicio FRONT')
            ->firstOrFail();

        $this->assertSame($organization->id, $service->organization_id);
        $this->assertSame($client->id, $service->client_id);
        $this->assertSame($assignee->id, $service->assigned_to);
        $this->assertSame('quotation', $service->stage);
        $this->assertSame('COT-001', $service->quotation_number);
        $this->assertTrue($service->includes_tax);

        $update = $this->actingAs($user)->post(
            '/servicios/'.$service->id.'/editar',
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'assigned_to' => $user->id,
                'title' => 'Servicio FRONT actualizado',
                'description' => 'Servicio actualizado desde FRONT.',
                'stage' => 'execution',
                'quotation_number' => 'COT-001',
                'quotation_date' => '2026-09-13',
                'order_number' => 'OS-001',
                'order_received_date' => '2026-09-14',
                'start_date' => '2026-09-15',
                'end_date' => '2026-10-20',
                'report_submitted_date' => null,
                'conformity_date' => null,
                'invoice_number' => null,
                'invoice_date' => null,
                'invoice_due_date' => null,
                'paid_date' => null,
                'closed_date' => null,
                'amount' => '1800',
                'invoice_amount' => null,
                'currency' => 'USD',
                'next_action' => 'Ejecutar entregable',
                'next_action_at' => '2026-09-18 09:00:00',
                'drive_url' => 'https://drive.google.com/example',
                'notes' => 'Actualización FRONT',
            ],
        );

        $update->assertRedirect();

        $service->refresh();
        $this->assertSame('Servicio FRONT actualizado', $service->title);
        $this->assertSame('execution', $service->stage);
        $this->assertSame($user->id, $service->assigned_to);
        $this->assertSame('USD', $service->currency);
        $this->assertFalse($service->includes_tax);

        $this->actingAs($user)
            ->get('/servicios/'.$service->id.'/editar')
            ->assertOk()
            ->assertSee('Servicio FRONT actualizado')
            ->assertSee('Guardar cambios')
            ->assertDontSee('/admin/ordenes-servicio', false);
    }

    public function test_service_has_separate_read_only_detail_view(): void
    {
        [$user, $organization] = $this->context('member');

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente detalle',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio visible en detalle',
            'stage' => 'execution',
            'currency' => 'PEN',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/servicios/'.$service->id)
            ->assertOk()
            ->assertSee('Detalle del servicio')
            ->assertSee('Servicio visible en detalle')
            ->assertSee('Compromiso contractual')
            ->assertSee('Órdenes vinculadas')
            ->assertSee('Facturación y cobranza')
            ->assertSee('Editar servicio')
            ->assertDontSee('name="title"', false)
            ->assertDontSee('Agregar hito / entregable')
            ->assertDontSee('Agregar hito y crear tarea');

        $this->actingAs($user)
            ->get('/servicios/'.$service->id.'/editar')
            ->assertOk()
            ->assertSee('Agregar hito / entregable')
            ->assertSee('Agregar hito y crear tarea');
    }

    public function test_service_detail_exposes_document_links_in_new_tabs(): void
    {
        [$user, $organization] = $this->context('member');

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente documentos',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio documentado',
            'contract_document_type' => 'contract',
            'contract_number' => 'C-2026-01',
            'contract_url' => 'https://docs.example.test/contrato',
            'stage' => 'execution',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $executionOrder = $service->executionOrders()->create([
            'fiscal_year' => 2026,
            'document_type' => 'service_order',
            'document_number' => 'OS-500',
            'document_url' => 'https://docs.example.test/orden',
            'status' => 'issued',
            'created_by' => $user->id,
        ]);

        $service->invoices()->create([
            'execution_order_id' => $executionOrder->id,
            'number' => 'E001-500',
            'document_url' => 'https://docs.example.test/factura',
            'status' => 'issued',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/servicios/'.$service->id)
            ->assertOk()
            ->assertSee('Abrir contrato')
            ->assertSee('Abrir orden')
            ->assertSee('Abrir factura')
            ->assertSee('https://docs.example.test/contrato', false)
            ->assertSee('https://docs.example.test/orden', false)
            ->assertSee('https://docs.example.test/factura', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_client_can_be_created_inline_from_service_order_form(): void
    {
        [$user, $organization] = $this->context('member');

        $response = $this->actingAs($user)
            ->postJson('/clientes', [
                'organization_id' => $organization->id,
                'name' => 'Cliente creado desde OS',
                'legal_name' => 'Cliente OS S.A.C.',
                'tax_id' => '20609999991',
                'contact_name' => 'Contacto OS',
                'email' => 'contacto-os@example.test',
                'phone' => '999888777',
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Cliente creado.')
            ->assertJsonPath('client.name', 'Cliente creado desde OS')
            ->assertJsonPath(
                'client.organization_ids.0',
                $organization->id,
            );

        $client = Client::query()
            ->where('name', 'Cliente creado desde OS')
            ->firstOrFail();

        $this->assertTrue($client->is_active);
        $this->assertTrue(
            $client->organizations()
                ->whereKey($organization->id)
                ->exists(),
        );

        $this->actingAs($user)
            ->get('/servicios/nuevo?scope='.$organization->id)
            ->assertOk()
            ->assertSee('Cliente creado desde OS');
    }

    public function test_viewer_can_read_service_but_cannot_create_or_update(): void
    {
        [$owner, $organization] = $this->context('owner');

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente lectura',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio solo lectura',
            'stage' => 'execution',
            'currency' => 'PEN',
            'created_by' => $owner->id,
        ]);

        $viewer = User::factory()->create([
            'email' => 'viewer-service-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/servicios/'.$service->id.'/editar')
            ->assertOk()
            ->assertSee('Servicio solo lectura')
            ->assertSee('solo lectura')
            ->assertDontSee('Guardar cambios');

        $payload = [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'No permitido',
            'stage' => 'execution',
            'currency' => 'PEN',
        ];

        $this->actingAs($viewer)
            ->post('/servicios', $payload)
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/servicios/'.$service->id.'/editar', $payload)
            ->assertForbidden();

        $this->assertSame('Servicio solo lectura', $service->fresh()->title);
    }

    public function test_service_front_rejects_cross_organization_relations_and_move(): void
    {
        [$user, $organization] = $this->context('owner');

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente principal',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $second = Organization::query()->create([
            'name' => 'Segundo ámbito servicio',
            'slug' => 'service-front-second',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $second->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'owner',
                'is_default' => false,
                'is_active' => true,
            ],
        ]);

        $foreignClient = Client::query()->create([
            'organization_id' => $second->id,
            'name' => 'Cliente segundo ámbito',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $invalidCreate = $this->actingAs($user)
            ->from('/servicios/nuevo')
            ->post('/servicios', [
                'organization_id' => $organization->id,
                'client_id' => $foreignClient->id,
                'title' => 'Relación inválida',
                'stage' => 'opportunity',
                'currency' => 'PEN',
            ]);

        $invalidCreate
            ->assertRedirect('/servicios/nuevo')
            ->assertSessionHasErrors('client_id');

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio fijo',
            'stage' => 'opportunity',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $move = $this->actingAs($user)
            ->from('/servicios/'.$service->id.'/editar')
            ->post('/servicios/'.$service->id.'/editar', [
                'organization_id' => $second->id,
                'client_id' => $foreignClient->id,
                'title' => 'Servicio fijo',
                'stage' => 'opportunity',
                'currency' => 'PEN',
            ]);

        $move
            ->assertRedirect('/servicios/'.$service->id.'/editar')
            ->assertSessionHasErrors('organization_id');

        $this->assertSame($organization->id, $service->fresh()->organization_id);
    }

    public function test_foreign_service_is_not_available_in_front(): void
    {
        [$user] = $this->context('owner');

        $foreignUser = User::factory()->create([
            'email' => 'foreign-service-front@arpynet.test',
            'is_active' => true,
        ]);

        $foreign = Organization::query()->create([
            'name' => 'Ámbito servicio ajeno',
            'slug' => 'service-front-foreign',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);

        $foreign->users()->attach($foreignUser->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $client = Client::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'Cliente ajeno',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $foreign->id,
            'client_id' => $client->id,
            'title' => 'Servicio oculto',
            'stage' => 'execution',
            'currency' => 'PEN',
            'created_by' => $foreignUser->id,
        ]);

        $this->actingAs($user)
            ->get('/servicios/'.$service->id.'/editar')
            ->assertForbidden();
    }

    private function context(string $role): array
    {
        $user = User::factory()->create([
            'email' => $role.'-service-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Servicios FRONT',
            'slug' => 'service-front-'.$role,
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => $role,
            'is_default' => true,
            'is_active' => true,
        ]);

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
