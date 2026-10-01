<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderExecutionOrder;
use App\Models\ServiceOrderInvoice;
use App\Models\ServiceOrderMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderContractExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiyear_contract_supports_multiple_orders_and_invoices(): void
    {
        [$user, $organization, $client] = $this->context();

        $create = $this->actingAs($user)->post('/servicios', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to' => $user->id,
            'title' => 'Plataforma de videoconferencias',
            'description' => 'Servicio anual multiejercicio.',
            'contract_document_type' => 'contract',
            'contract_number' => '008-2026-OS-CM-SUNARP',
            'contract_date' => '2026-09-01',
            'contract_amount' => '36500.00',
            'stage' => 'execution',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'amount' => '36500.00',
            'currency' => 'PEN',
            'includes_tax' => '1',
        ]);

        $create->assertRedirect();

        $service = ServiceOrder::query()
            ->where('contract_number', '008-2026-OS-CM-SUNARP')
            ->firstOrFail();

        $this->assertSame('36500.00', $service->contract_amount);

        $this->actingAs($user)->post(
            '/servicios/'.$service->id.'/ordenes-ejecucion',
            [
                'fiscal_year' => 2026,
                'document_type' => 'service_order',
                'document_number' => '0000507',
                'issued_date' => '2026-09-15',
                'amount' => '14600.00',
                'status' => 'issued',
                'notes' => 'Ejecución presupuestal 2026.',
            ],
        )->assertRedirect();

        $this->actingAs($user)->post(
            '/servicios/'.$service->id.'/ordenes-ejecucion',
            [
                'fiscal_year' => 2027,
                'document_type' => 'service_order',
                'document_number' => null,
                'amount' => '21900.00',
                'status' => 'pending',
                'notes' => 'Se emitirá en el siguiente ejercicio.',
            ],
        )->assertRedirect();

        $order2026 = ServiceOrderExecutionOrder::query()
            ->where('service_order_id', $service->id)
            ->where('fiscal_year', 2026)
            ->firstOrFail();

        $this->actingAs($user)->post(
            '/servicios/'.$service->id.'/facturas',
            [
                'execution_order_id' => $order2026->id,
                'number' => 'E001-5000',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-31',
                'amount' => '14600.00',
                'status' => 'issued',
                'notes' => 'Factura correspondiente al ejercicio 2026.',
            ],
        )->assertRedirect();

        $service->load(['executionOrders', 'invoices']);

        $this->assertCount(2, $service->executionOrders);
        $this->assertCount(1, $service->invoices);
        $this->assertSame(36500.0, $service->execution_ordered_amount);
        $this->assertSame(14600.0, $service->execution_issued_amount);
        $this->assertSame(14600.0, $service->invoiced_total);
        $this->assertSame(0.0, $service->paid_total);

        $invoice = ServiceOrderInvoice::query()->firstOrFail();
        $this->assertSame($order2026->id, $invoice->execution_order_id);
        $this->assertSame('14600.00', $invoice->amount);

        $this->actingAs($user)
            ->get('/servicios/'.$service->id.'/editar')
            ->assertOk()
            ->assertSee('Órdenes / ejecución presupuestal')
            ->assertSee('Facturación y cobranza')
            ->assertSee('0000507')
            ->assertSee('E001-5000');
    }

    public function test_milestone_quick_actions_separate_task_and_contract_status(): void
    {
        [$user, $organization, $client] = $this->context();

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio con entregable',
            'stage' => 'execution',
            'currency' => 'PEN',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $executionOrder = $service->executionOrders()->create([
            'fiscal_year' => 2026,
            'document_type' => 'service_order',
            'document_number' => '0000507',
            'amount' => '14600',
            'status' => 'issued',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(
            '/servicios/'.$service->id.'/hitos',
            [
                'title' => 'Informe de ejecución inicial',
                'contractual_due_date' => '2026-10-30',
                'execution_order_id' => $executionOrder->id,
                'assigned_to' => $user->id,
                'urgency' => 'high',
                'amount' => '14600',
            ],
        )->assertRedirect();

        $milestone = ServiceOrderMilestone::query()->firstOrFail();
        $this->assertSame($executionOrder->id, $milestone->execution_order_id);
        $this->assertSame('Pendiente', $milestone->contractual_status_label);
        $this->assertSame('Pendiente', $milestone->task_status_label);

        $this->actingAs($user)->post(
            '/servicios/'.$service->id.'/hitos/'.$milestone->id.'/accion',
            ['action' => 'complete_task'],
        )->assertRedirect();

        $milestone->refresh()->load('task');
        $this->assertSame('completed', $milestone->task->status);
        $this->assertSame('Completada', $milestone->task_status_label);
        $this->assertSame('Pendiente', $milestone->contractual_status_label);

        $this->actingAs($user)->post(
            '/servicios/'.$service->id.'/hitos/'.$milestone->id.'/accion',
            ['action' => 'mark_delivered'],
        )->assertRedirect();

        $milestone->refresh();
        $this->assertNotNull($milestone->delivered_date);
        $this->assertSame('Entregado', $milestone->contractual_status_label);

        $this->actingAs($user)->post(
            '/servicios/'.$service->id.'/hitos/'.$milestone->id.'/accion',
            ['action' => 'mark_conformity'],
        )->assertRedirect();

        $milestone->refresh();
        $this->assertNotNull($milestone->conformity_date);
        $this->assertSame('Conforme', $milestone->contractual_status_label);
    }

    public function test_viewer_cannot_add_execution_order_or_invoice(): void
    {
        [$owner, $organization, $client] = $this->context();

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio protegido',
            'stage' => 'execution',
            'currency' => 'PEN',
            'created_by' => $owner->id,
        ]);

        $viewer = User::factory()->create([
            'email' => 'viewer-contract-execution@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)->post(
            '/servicios/'.$service->id.'/ordenes-ejecucion',
            [
                'document_type' => 'service_order',
                'status' => 'pending',
            ],
        )->assertForbidden();

        $this->actingAs($viewer)->post(
            '/servicios/'.$service->id.'/facturas',
            [
                'status' => 'pending',
            ],
        )->assertForbidden();

        $this->assertDatabaseCount('service_order_execution_orders', 0);
        $this->assertDatabaseCount('service_order_invoices', 0);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'contract-execution@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Contratos',
            'slug' => 'arpynet-contratos',
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

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'SUNARP',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return [$user, $organization, $client];
    }
}
