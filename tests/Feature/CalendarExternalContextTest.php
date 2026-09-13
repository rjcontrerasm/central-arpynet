<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\GoogleCalendarAgendaReader;
use App\Support\CalendarExternalContextBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CalendarExternalContextTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_context_calculates_union_busy_time_current_next_and_overlaps(): void
    {
        $builder = app(CalendarExternalContextBuilder::class);
        $day = CarbonImmutable::parse('2026-09-14 00:00:00', 'America/Lima');
        $now = CarbonImmutable::parse('2026-09-14 09:45:00', 'America/Lima');

        $context = $builder->build([
            'connected' => true,
            'status' => 'ok',
            'events' => [
                $this->event('a', 'Primera reunión', '09:00', '10:00'),
                $this->event('b', 'Reunión superpuesta', '09:30', '11:00'),
                $this->event('c', 'Reunión de la tarde', '14:00', '15:00'),
            ],
        ], $day, $now);

        $this->assertTrue($context['has_context']);
        $this->assertSame(3, $context['counts']['external']);
        $this->assertSame(180, $context['busy_minutes']);
        $this->assertSame(1, $context['overlap_count']);
        $this->assertSame('Primera reunión', $context['current_event']['title']);
        $this->assertSame('Reunión de la tarde', $context['next_event']['title']);
        $this->assertTrue($context['read_only_context']);
        $this->assertFalse($context['availability_inferred']);
    }

    public function test_all_day_events_are_counted_but_do_not_invent_busy_minutes(): void
    {
        $builder = app(CalendarExternalContextBuilder::class);
        $day = CarbonImmutable::parse('2026-09-14 00:00:00', 'America/Lima');

        $context = $builder->build([
            'connected' => true,
            'status' => 'ok',
            'events' => [[
                'key' => 'google:all-day',
                'source' => 'google_calendar',
                'kind' => 'calendar',
                'title' => 'Evento de todo el día',
                'subtitle' => '',
                'starts_at' => $day,
                'ends_at' => $day->addDay(),
                'all_day' => true,
                'url' => null,
                'external' => true,
            ]],
        ], $day, $day->setTime(8, 0));

        $this->assertSame(1, $context['counts']['all_day']);
        $this->assertSame(0, $context['counts']['timed']);
        $this->assertSame(0, $context['busy_minutes']);
        $this->assertCount(1, $context['all_day_events']);
    }

    public function test_disconnected_calendar_returns_safe_empty_context(): void
    {
        $builder = app(CalendarExternalContextBuilder::class);
        $day = CarbonImmutable::parse('2026-09-14 00:00:00', 'America/Lima');

        $context = $builder->build([
            'connected' => false,
            'status' => 'disconnected',
            'events' => [],
        ], $day, $day->setTime(10, 0));

        $this->assertFalse($context['has_context']);
        $this->assertSame('disconnected', $context['status']);
        $this->assertSame(0, $context['busy_minutes']);
        $this->assertSame(0, $context['overlap_count']);
        $this->assertNull($context['current_event']);
        $this->assertNull($context['next_event']);
    }

    public function test_operational_agenda_exposes_calendar_context_without_second_google_read(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 08:00:00');
        [$user, $organization] = $this->context();

        $reader = Mockery::mock(GoogleCalendarAgendaReader::class);
        $reader->shouldReceive('eventsFor')->once()->andReturn([
            'connected' => true,
            'status' => 'ok',
            'error' => null,
            'events' => [
                $this->event('meeting', 'Reunión externa', '11:00', '12:00'),
            ],
        ]);
        $this->app->instance(GoogleCalendarAgendaReader::class, $reader);

        $response = $this->actingAs($user)
            ->get('/agenda?date=2026-09-14&scope='.$organization->id)
            ->assertOk();

        $response->assertViewHas('calendarContext', function (array $context): bool {
            return $context['counts']['external'] === 1
                && $context['busy_minutes'] === 60
                && $context['next_event']['title'] === 'Reunión externa'
                && $context['read_only_context'] === true
                && $context['availability_inferred'] === false;
        });
    }

    private function event(
        string $id,
        string $title,
        string $start,
        string $end,
    ): array {
        return [
            'key' => 'google:'.$id,
            'source' => 'google_calendar',
            'kind' => 'calendar',
            'title' => $title,
            'subtitle' => 'Google Meet',
            'starts_at' => CarbonImmutable::parse('2026-09-14 '.$start.':00', 'America/Lima'),
            'ends_at' => CarbonImmutable::parse('2026-09-14 '.$end.':00', 'America/Lima'),
            'all_day' => false,
            'organization' => null,
            'url' => 'https://calendar.google.com/',
            'external' => true,
        ];
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'calendar-context@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Calendar Context',
            'slug' => 'arpynet-calendar-context',
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

        return [$user, $organization];
    }
}
