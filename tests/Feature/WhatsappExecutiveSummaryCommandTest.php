<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappExecutiveSummaryCommandTest
    extends TestCase
{
    use RefreshDatabase;

    private const WA_ID =
        '51999999999';

    public function test_command_is_disabled_by_default(): void
    {
        config()->set(
            'central.summary_whatsapp.enabled',
            false,
        );

        Http::fake();

        $exit = Artisan::call(
            'summary:whatsapp',
            ['period' => 'today'],
        );

        $this->assertSame(0, $exit);

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'summary_whatsapp_deliveries',
            0,
        );
    }

    public function test_enabled_command_sends_template(): void
    {
        [$user] = $this->context();

        config()->set(
            'central.summary_whatsapp.enabled',
            true,
        );

        config()->set(
            'central.summary_whatsapp.user_email',
            $user->email,
        );

        config()->set(
            'central.summary_whatsapp.template',
            'central_executive_summary',
        );

        config()->set(
            'central.summary_whatsapp.language',
            'es',
        );

        config()->set(
            'whatsapp.allowed_wa_ids',
            [self::WA_ID],
        );

        config()->set(
            'whatsapp.outbound_enabled',
            true,
        );

        config()->set(
            'whatsapp.access_token',
            'test-access-token',
        );

        config()->set(
            'whatsapp.phone_number_id',
            'phone-test',
        );

        config()->set(
            'whatsapp.graph_version',
            'v26.0',
        );

        Http::fake([
            'graph.facebook.com/*' =>
                Http::response(
                    [
                        'messages' => [[
                            'id' =>
                                'wamid.summary.1',
                        ]],
                    ],
                    200,
                ),
        ]);

        $exit = Artisan::call(
            'summary:whatsapp',
            [
                'period' => 'today',
                '--force' => true,
            ],
        );

        $this->assertSame(0, $exit);

        $this->assertDatabaseHas(
            'summary_whatsapp_deliveries',
            [
                'user_id' => $user->id,
                'period' => 'today',
                'status' => 'sent',
                'message_id' =>
                    'wamid.summary.1',
            ],
        );

        Http::assertSent(
            function (Request $request): bool {
                return
                    $request->method() === 'POST'
                    && $request->url()
                        === 'https://graph.facebook.com/v26.0/phone-test/messages'
                    && $request['to']
                        === self::WA_ID
                    && $request['type']
                        === 'template'
                    && data_get(
                        $request->data(),
                        'template.name',
                    )
                        === 'central_executive_summary'
                    && count(
                        data_get(
                            $request->data(),
                            'template.components.0.parameters',
                            [],
                        ),
                    ) === 9;
            },
        );
    }

    public function test_invalid_period_fails_when_enabled(): void
    {
        config()->set(
            'central.summary_whatsapp.enabled',
            true,
        );

        $exit = Artisan::call(
            'summary:whatsapp',
            ['period' => 'month'],
        );

        $this->assertSame(1, $exit);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' => 'arpynet',
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

        return [$user, $organization];
    }
}
