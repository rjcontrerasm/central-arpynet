<?php

namespace Tests\Feature;

use App\Services\GoogleCalendarAgendaReader;
use Google\Service\Calendar\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarAgendaReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_generated_events_are_excluded_to_avoid_duplicates(): void
    {
        $reader=app(GoogleCalendarAgendaReader::class);
        $central=new Event(['id'=>'central-1','summary'=>'Tarea Central','start'=>['dateTime'=>'2026-09-03T10:00:00-05:00'],'end'=>['dateTime'=>'2026-09-03T10:30:00-05:00'],'extendedProperties'=>['private'=>['central_app'=>'central-arpynet']]]);
        $external=new Event(['id'=>'external-1','summary'=>'Reunión externa','location'=>'Google Meet','start'=>['dateTime'=>'2026-09-03T11:00:00-05:00'],'end'=>['dateTime'=>'2026-09-03T11:30:00-05:00'],'htmlLink'=>'https://calendar.google.com/']);
        $events=$reader->externalEvents([$central,$external],'America/Lima');
        $this->assertCount(1,$events);
        $this->assertSame('Reunión externa',$events[0]['title']);
    }

    public function test_all_day_external_event_is_normalized(): void
    {
        $reader=app(GoogleCalendarAgendaReader::class);
        $event=new Event(['id'=>'all-day-1','summary'=>'Feriado interno','start'=>['date'=>'2026-09-03'],'end'=>['date'=>'2026-09-04']]);
        $events=$reader->externalEvents([$event],'America/Lima');
        $this->assertTrue($events[0]['all_day']);
        $this->assertSame('2026-09-03',$events[0]['starts_at']->format('Y-m-d'));
    }
}
