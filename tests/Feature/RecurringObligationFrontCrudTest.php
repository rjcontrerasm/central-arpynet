<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringObligationFrontCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_member_can_create_and_edit_recurring_obligation_in_front(): void
    {
        Carbon::setTestNow('2026-09-13 10:00:00');
        [$user, $organization] = $this->context('member');

        $this->actingAs($user)
            ->get('/vencimientos/recurrentes')
            ->assertOk()
            ->assertSee('Obligaciones recurrentes')
            ->assertDontSee('/admin/obligaciones-recurrentes', false);

        $this->actingAs($user)
            ->get('/vencimientos/recurrentes/nueva')
            ->assertOk()
            ->assertSee('Nueva obligación');

        $create = $this->actingAs($user)->post(
            '/vencimientos/recurrentes',
            [
                'organization_id' => $organization->id,
                'name' => 'Cloud mensual',
                'category' => 'subscription',
                'description' => 'Servicio cloud recurrente.',
                'frequency' => 'monthly',
                'anchor_date' => '2026-09-20',
                'end_date' => '2027-09-20',
                'expected_amount' => '250.50',
                'currency' => 'USD',
                'reminder_days_before' => 5,
                'is_critical' => '1',
                'is_active' => '1',
                'provider' => 'Proveedor Cloud',
                'reference' => 'SUB-001',
                'drive_url' => 'https://drive.google.com/example',
                'notes' => 'Controlado desde FRONT.',
            ],
        );

        $create->assertRedirect();

        $obligation = RecurringObligation::query()
            ->where('name', 'Cloud mensual')
            ->firstOrFail();

        $this->assertSame($organization->id, $obligation->organization_id);
        $this->assertSame('monthly', $obligation->frequency);
        $this->assertSame('USD', $obligation->currency);
        $this->assertTrue($obligation->is_critical);
        $this->assertTrue($obligation->is_active);
        $this->assertGreaterThan(0, $obligation->occurrences()->count());

        $update = $this->actingAs($user)->post(
            '/vencimientos/recurrentes/'.$obligation->id.'/editar',
            [
                'organization_id' => $organization->id,
                'name' => 'Cloud mensual actualizado',
                'category' => 'subscription',
                'frequency' => 'monthly',
                'anchor_date' => '2026-09-20',
                'end_date' => '2027-09-20',
                'expected_amount' => '300',
                'currency' => 'USD',
                'reminder_days_before' => 7,
                'provider' => 'Proveedor Cloud',
                'reference' => 'SUB-001',
            ],
        );

        $update->assertRedirect();

        $obligation->refresh();
        $this->assertSame('Cloud mensual actualizado', $obligation->name);
        $this->assertFalse($obligation->is_critical);
        $this->assertFalse($obligation->is_active);
        $this->assertSame(7, $obligation->reminder_days_before);
    }

    public function test_viewer_can_read_but_cannot_create_or_update_recurring_obligation(): void
    {
        [$owner, $organization] = $this->context('owner');

        $obligation = RecurringObligation::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Licencia solo lectura',
            'category' => 'license',
            'frequency' => 'annual',
            'anchor_date' => now()->addMonth()->toDateString(),
            'currency' => 'USD',
            'reminder_days_before' => 10,
            'is_active' => false,
            'created_by' => $owner->id,
        ]);

        $viewer = User::factory()->create([
            'email' => 'viewer-obligation-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/vencimientos/recurrentes/'.$obligation->id.'/editar')
            ->assertOk()
            ->assertSee('Licencia solo lectura')
            ->assertSee('solo lectura')
            ->assertDontSee('Guardar cambios');

        $payload = [
            'organization_id' => $organization->id,
            'name' => 'No permitido',
            'category' => 'license',
            'frequency' => 'annual',
            'anchor_date' => now()->addMonth()->toDateString(),
            'currency' => 'USD',
            'reminder_days_before' => 10,
            'is_active' => '1',
        ];

        $this->actingAs($viewer)
            ->post('/vencimientos/recurrentes', $payload)
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(
                '/vencimientos/recurrentes/'.$obligation->id.'/editar',
                $payload,
            )
            ->assertForbidden();

        $this->assertSame('Licencia solo lectura', $obligation->fresh()->name);
    }

    public function test_recurring_obligation_cannot_move_between_organizations(): void
    {
        [$user, $organization] = $this->context('owner');

        $second = Organization::query()->create([
            'name' => 'Segundo ámbito obligación',
            'slug' => 'obligation-front-second',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $second->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => false,
            'is_active' => true,
        ]);

        $obligation = RecurringObligation::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Dominio principal',
            'category' => 'domain',
            'frequency' => 'annual',
            'anchor_date' => now()->addMonth()->toDateString(),
            'currency' => 'USD',
            'reminder_days_before' => 30,
            'is_active' => false,
            'created_by' => $user->id,
        ]);

        $move = $this->actingAs($user)
            ->from('/vencimientos/recurrentes/'.$obligation->id.'/editar')
            ->post(
                '/vencimientos/recurrentes/'.$obligation->id.'/editar',
                [
                    'organization_id' => $second->id,
                    'name' => 'Dominio principal',
                    'category' => 'domain',
                    'frequency' => 'annual',
                    'anchor_date' => now()->addMonth()->toDateString(),
                    'currency' => 'USD',
                    'reminder_days_before' => 30,
                ],
            );

        $move
            ->assertRedirect('/vencimientos/recurrentes/'.$obligation->id.'/editar')
            ->assertSessionHasErrors('organization_id');

        $this->assertSame(
            $organization->id,
            $obligation->fresh()->organization_id,
        );
    }

    public function test_foreign_recurring_obligation_is_forbidden(): void
    {
        [$user] = $this->context('owner');

        $foreignUser = User::factory()->create([
            'email' => 'foreign-obligation-front@arpynet.test',
            'is_active' => true,
        ]);

        $foreign = Organization::query()->create([
            'name' => 'Ámbito obligación ajeno',
            'slug' => 'obligation-front-foreign',
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

        $obligation = RecurringObligation::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'Obligación oculta',
            'category' => 'other',
            'frequency' => 'annual',
            'anchor_date' => now()->addMonth()->toDateString(),
            'currency' => 'PEN',
            'reminder_days_before' => 7,
            'is_active' => false,
            'created_by' => $foreignUser->id,
        ]);

        $this->actingAs($user)
            ->get('/vencimientos/recurrentes/'.$obligation->id.'/editar')
            ->assertForbidden();
    }

    private function context(string $role): array
    {
        $user = User::factory()->create([
            'email' => $role.'-obligation-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Obligaciones FRONT',
            'slug' => 'obligation-front-'.$role,
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
