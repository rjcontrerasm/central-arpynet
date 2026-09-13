<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontWriteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_mutate_service_operations_or_finance(): void
    {
        [$owner, $viewer, $organization] = $this->context();

        $this->actingAs($owner);

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente servicio viewer',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio protegido',
            'stage' => 'execution',
            'currency' => 'PEN',
            'includes_tax' => true,
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
        ]);

        $this->actingAs($viewer)
            ->post('/servicios/'.$service->id.'/actualizar', [
                'stage' => 'conformity',
                'next_action' => 'Intento viewer',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/servicios/'.$service->id.'/finanzas', [
                'amount' => '5000',
                'currency' => 'PEN',
                'includes_tax' => '1',
                'invoice_number' => 'F001-999',
            ])
            ->assertForbidden();

        $service->refresh();
        $this->assertSame('execution', $service->stage);
        $this->assertNull($service->next_action);
        $this->assertNull($service->invoice_number);
    }

    public function test_viewer_cannot_mutate_obligation_occurrence(): void
    {
        [$owner, $viewer, $organization] = $this->context();

        $this->actingAs($owner);

        $obligation = RecurringObligation::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Obligación protegida',
            'category' => 'service',
            'frequency' => 'monthly',
            'anchor_date' => now()->toDateString(),
            'expected_amount' => 100,
            'currency' => 'PEN',
            'reminder_days_before' => 7,
            'is_critical' => false,
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $occurrence = ObligationOccurrence::query()
            ->where('recurring_obligation_id', $obligation->id)
            ->orderBy('due_date')
            ->firstOrFail();

        $this->actingAs($viewer)
            ->post('/vencimientos/'.$occurrence->id.'/actualizar', [
                'action' => 'paid',
                'actual_amount' => '100',
                'paid_date' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame('pending', $occurrence->fresh()->status);
    }

    private function context(): array
    {
        $owner = User::factory()->create([
            'email' => 'owner-front-write@arpynet.test',
            'is_active' => true,
        ]);

        $this->actingAs($owner);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Front Write Boundary',
            'slug' => 'front-write-boundary',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $owner->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        $viewer = User::factory()->create([
            'email' => 'viewer-front-write@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        return [$owner, $viewer, $organization];
    }
}
