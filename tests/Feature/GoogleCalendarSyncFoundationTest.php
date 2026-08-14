<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Services\GoogleCalendarPayloadFactory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarSyncFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_task_becomes_all_day_calendar_event(): void
    {
        Carbon::setTestNow('2026-08-14 01:00:00');

        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Presentar informe',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => '2026-08-20 17:00:00',
            'created_by' => $user->id,
        ]);

        $payload = app(
            GoogleCalendarPayloadFactory::class,
        )->task($task->fresh(['organization']));

        $this->assertSame(
            'task',
            $payload['source_type'],
        );

        $this->assertSame(
            '2026-08-20',
            $payload['start']['date'],
        );

        $this->assertSame(
            '2026-08-21',
            $payload['end']['date'],
        );
    }

    public function test_obligation_becomes_all_day_calendar_event(): void
    {
        [$user, $organization] = $this->context();

        $obligation = RecurringObligation::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Dominio arpynet.com',
            'category' => 'domain',
            'frequency' => 'annual',
            'anchor_date' => '2026-09-01',
            'currency' => 'USD',
            'is_active' => false,
            'created_by' => $user->id,
        ]);

        $occurrence = ObligationOccurrence::query()->create([
            'recurring_obligation_id' => $obligation->id,
            'organization_id' => $organization->id,
            'due_date' => '2026-09-01',
            'status' => 'pending',
            'currency' => 'USD',
        ]);

        $payload = app(
            GoogleCalendarPayloadFactory::class,
        )->obligation(
            $occurrence->fresh([
                'organization',
                'obligation',
            ]),
        );

        $this->assertSame(
            'obligation',
            $payload['source_type'],
        );

        $this->assertSame(
            '2026-09-01',
            $payload['start']['date'],
        );
    }

    public function test_follow_up_becomes_thirty_minute_event(): void
    {
        Carbon::setTestNow('2026-08-14 01:00:00');

        [$user, $organization] = $this->context();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente Calendar',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $order = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio mensual',
            'stage' => 'execution',
            'next_action' => 'Llamar al cliente',
            'next_action_at' => '2026-08-14 10:00:00',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $payload = app(
            GoogleCalendarPayloadFactory::class,
        )->serviceOrder(
            $order->fresh([
                'organization',
                'client',
            ]),
        );

        $start = Carbon::parse(
            $payload['start']['dateTime'],
        );

        $end = Carbon::parse(
            $payload['end']['dateTime'],
        );

        $this->assertSame(
            30,
            (int) $start->diffInMinutes($end),
        );
    }

    public function test_incident_follow_up_becomes_calendar_event(): void
    {
        Carbon::setTestNow('2026-08-14 01:00:00');

        [$user, $organization] = $this->context();

        $incident = Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'CPU elevada',
            'severity' => 'high',
            'status' => 'investigating',
            'next_action' => 'Revisar CloudWatch',
            'next_action_at' => '2026-08-14 11:00:00',
            'created_by' => $user->id,
        ]);

        $payload = app(
            GoogleCalendarPayloadFactory::class,
        )->incident(
            $incident->fresh([
                'organization',
                'client',
            ]),
        );

        $this->assertSame(
            'incident',
            $payload['source_type'],
        );

        $this->assertStringContainsString(
            'CPU elevada',
            $payload['summary'],
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

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return [$user, $organization];
    }
}
