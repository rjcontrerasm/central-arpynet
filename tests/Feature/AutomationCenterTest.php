<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_center_requires_authentication(): void
    {
        $this->get('/automatizaciones')
            ->assertRedirect('/login');
    }

    public function test_center_creates_inactive_safe_rule_for_member_organization(): void
    {
        [$user, $organization] = $this->context();

        $this->actingAs($user)->post(
            route('automation-center.store'),
            [
                'organization_id' => $organization->id,
                'name' => 'Cobranza ARPYNET',
                'trigger_key' => 'service.invoice_overdue',
                'action_key' => 'service.create_collection_reminder',
                'mode' => 'automatic',
            ],
        )->assertRedirect(route('automation-center.index'));

        $this->assertDatabaseHas('automation_rules', [
            'organization_id' => $organization->id,
            'name' => 'Cobranza ARPYNET',
            'mode' => 'automatic',
            'is_active' => 0,
        ]);
    }

    public function test_automatic_task_mutation_is_rejected(): void
    {
        [$user, $organization] = $this->context();

        $this->actingAs($user)
            ->from(route('automation-center.index'))
            ->post(
                route('automation-center.store'),
                [
                    'organization_id' => $organization->id,
                    'name' => 'No permitido',
                    'trigger_key' => 'task.overdue',
                    'action_key' => 'task.raise_attention',
                    'mode' => 'automatic',
                ],
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('automation_rules', 0);
    }

    public function test_rule_of_other_organization_is_forbidden(): void
    {
        [$user] = $this->context();

        $otherUser = User::factory()->create();

        $other = Organization::query()->create([
            'name' => 'OTHER',
            'slug' => 'other-automation-center',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $otherUser->id,
        ]);

        $rule = AutomationRule::query()->create([
            'organization_id' => $other->id,
            'name' => 'Regla ajena',
            'trigger_key' => 'task.overdue',
            'action_key' => 'task.raise_attention',
            'mode' => 'preview',
            'is_active' => false,
            'created_by' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->post(route('automation-center.toggle', $rule))
            ->assertForbidden();
    }

    public function test_preview_is_read_only_and_manual_run_creates_internal_notification(): void
    {
        CarbonImmutable::setTestNow('2026-09-04 10:00:00');

        [$user, $organization, $client] = $this->context(true);

        $order = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Factura vencida UI',
            'stage' => 'invoiced',
            'invoice_number' => 'F001-UI',
            'invoice_amount' => 900,
            'invoice_due_date' => '2026-09-01',
            'currency' => 'PEN',
            'includes_tax' => true,
            'created_by' => $user->id,
        ]);

        $before = $order->fresh()->getAttributes();

        $rule = AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cobranza UI',
            'trigger_key' => 'service.invoice_overdue',
            'action_key' => 'service.create_collection_reminder',
            'mode' => 'automatic',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('automation-center.preview', $rule))
            ->assertRedirect(route('automation-center.index'));

        $this->assertDatabaseCount('automation_rule_runs', 0);
        $this->assertSame($before, $order->fresh()->getAttributes());

        $this->actingAs($user)
            ->post(route('automation-center.run', $rule))
            ->assertRedirect(route('automation-center.index'));

        $this->assertDatabaseHas('automation_rule_runs', [
            'automation_rule_id' => $rule->id,
            'outcome' => 'executed',
        ]);

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_center_lists_member_rules(): void
    {
        [$user, $organization] = $this->context();

        AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Regla visible',
            'trigger_key' => 'task.overdue',
            'action_key' => 'task.raise_attention',
            'mode' => 'preview',
            'is_active' => false,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('automation-center.index'))
            ->assertOk()
            ->assertSee('Automatizaciones')
            ->assertSee('Regla visible')
            ->assertSee('El scheduler y los canales externos siguen deshabilitados.');
    }

    private function context(bool $withClient = false): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-automation-center',
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

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        if (! $withClient) {
            return [$user, $organization];
        }

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente UI Automation',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return [$user, $organization, $client];
    }
}
