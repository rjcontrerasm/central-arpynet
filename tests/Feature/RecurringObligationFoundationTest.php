<?php

namespace Tests\Feature;

use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\User;
use App\Support\ObligationOccurrenceGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringObligationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_monthly_obligation_generates_future_occurrences(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Personal',
            'slug' => 'personal',
            'category' => 'personal',
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

        $obligation = RecurringObligation::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Luz',
            'category' => 'service',
            'frequency' => 'monthly',
            'anchor_date' => '2026-08-15',
            'expected_amount' => 150,
            'currency' => 'PEN',
            'reminder_days_before' => 7,
            'is_active' => true,
        ]);

        $countBeforeSecondGeneration =
            $obligation->occurrences()->count();

        $createdAgain = app(
            ObligationOccurrenceGenerator::class,
        )->generateFor(
            $obligation,
            now()->startOfDay(),
            now()->addDays(70)->endOfDay(),
        );

        $this->assertSame(0, $createdAgain);

        $this->assertSame(
            $countBeforeSecondGeneration,
            $obligation->occurrences()->count(),
        );

        $this->assertTrue(
            ObligationOccurrence::query()
                ->where(
                    'recurring_obligation_id',
                    $obligation->id,
                )
                ->whereDate('due_date', '2026-08-15')
                ->where('status', 'pending')
                ->exists(),
        );

        $this->assertTrue(
            ObligationOccurrence::query()
                ->where(
                    'recurring_obligation_id',
                    $obligation->id,
                )
                ->whereDate('due_date', '2026-09-15')
                ->where('status', 'pending')
                ->exists(),
        );
    }

    public function test_paid_occurrence_records_completion(): void
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

        $obligation = RecurringObligation::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Licencia',
            'category' => 'license',
            'frequency' => 'annual',
            'anchor_date' => '2026-08-20',
            'currency' => 'USD',
            'is_active' => false,
            'created_by' => $user->id,
        ]);

        $occurrence = ObligationOccurrence::query()->create([
            'recurring_obligation_id' => $obligation->id,
            'organization_id' => $organization->id,
            'due_date' => '2026-08-20',
            'status' => 'paid',
            'actual_amount' => 100,
            'currency' => 'USD',
        ]);

        $this->assertNotNull($occurrence->paid_date);
        $this->assertNotNull($occurrence->completed_at);
        $this->assertSame('Pagado', $occurrence->attention_label);
    }
}
