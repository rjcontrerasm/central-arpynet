<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Services\GoogleCalendarAgendaReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OperationalAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_agenda_combines_central_items_and_external_calendar_event(): void
    {
        CarbonImmutable::setTestNow('2026-09-03 08:00:00');
        [$user,$organization]=$this->context();

        Task::query()->create(['organization_id'=>$organization->id,'title'=>'Preparar propuesta','status'=>'pending','urgency'=>'normal','impact'=>'high','due_at'=>'2026-09-03 11:00:00','created_by'=>$user->id]);
        $client=Client::query()->create(['organization_id'=>$organization->id,'name'=>'Cliente Agenda','is_active'=>true,'created_by'=>$user->id]);
        ServiceOrder::query()->create(['organization_id'=>$organization->id,'client_id'=>$client->id,'title'=>'Seguimiento comercial','stage'=>'opportunity','next_action'=>'Llamar al cliente','next_action_at'=>'2026-09-03 14:00:00','currency'=>'PEN','includes_tax'=>true,'created_by'=>$user->id]);

        $obligation=RecurringObligation::withoutEvents(fn()=>RecurringObligation::query()->create(['organization_id'=>$organization->id,'name'=>'Renovar dominio','category'=>'service','frequency'=>'annual','anchor_date'=>'2026-09-03','currency'=>'PEN','reminder_days_before'=>7,'is_critical'=>true,'is_active'=>true,'created_by'=>$user->id]));
        ObligationOccurrence::query()->create(['recurring_obligation_id'=>$obligation->id,'organization_id'=>$organization->id,'due_date'=>'2026-09-03','status'=>'pending','currency'=>'PEN']);

        $reader=Mockery::mock(GoogleCalendarAgendaReader::class);
        $reader->shouldReceive('eventsFor')->once()->andReturn(['connected'=>true,'status'=>'ok','error'=>null,'events'=>[[
            'key'=>'google:abc','source'=>'google_calendar','kind'=>'calendar','title'=>'Reunión externa','subtitle'=>'Google Meet','starts_at'=>CarbonImmutable::parse('2026-09-03 09:30:00'),'ends_at'=>CarbonImmutable::parse('2026-09-03 10:00:00'),'all_day'=>false,'organization'=>null,'priority'=>null,'url'=>'https://calendar.google.com/','external'=>true,
        ]]]);
        $this->app->instance(GoogleCalendarAgendaReader::class,$reader);

        $this->actingAs($user)->get('/agenda?date=2026-09-03')->assertOk()->assertSee('Agenda')->assertSee('Reunión externa')->assertSee('Preparar propuesta')->assertSee('Seguimiento comercial')->assertSee('Renovar dominio')->assertSee('Google Calendar conectado');
    }

    public function test_foreign_scope_is_forbidden(): void
    {
        [$user]=$this->context();
        $foreign=Organization::query()->create(['name'=>'Ajena agenda','slug'=>'ajena-agenda','category'=>'company','timezone'=>'America/Lima','is_active'=>true,'created_by'=>$user->id]);
        $this->actingAs($user)->get('/agenda?scope='.$foreign->id)->assertForbidden();
    }

    private function context(): array
    {
        $user=User::factory()->create(['email'=>'rcontreras@arpynet.com']);
        $organization=Organization::query()->create(['name'=>'ARPYNET','slug'=>'arpynet-agenda','category'=>'company','timezone'=>'America/Lima','is_active'=>true,'created_by'=>$user->id]);
        $organization->users()->attach($user->id,['role'=>'owner','is_default'=>true,'is_active'=>true]);
        $user->forceFill(['current_organization_id'=>$organization->id])->save();
        return [$user,$organization];
    }
}
