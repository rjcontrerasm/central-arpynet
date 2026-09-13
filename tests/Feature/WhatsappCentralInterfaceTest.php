<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappCentralInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';
    private const VERIFY_TOKEN = 'test-verify-token';
    private const WA_ID = '51999999999';
    private const SECOND_WA_ID = '51988888888';

    public function test_slash_text_keeps_legacy_task_capture_when_commands_are_disabled(): void
    {
        $this->enableWebhook(false, false);
        [$user, $organization] = $this->context();

        $payload = $this->payload(
            'wamid.command.disabled.1',
            self::WA_ID,
            '/resumen',
        );

        $this->postRaw($payload)
            ->assertOk()
            ->assertJson(['processed' => 1]);

        $this->assertDatabaseHas('tasks', [
            'organization_id' => $organization->id,
            'title' => '/resumen',
            'created_by' => $user->id,
        ]);
    }

    public function test_help_command_is_read_only_and_replies_through_safe_outbound(): void
    {
        $this->enableWebhook(true, true);
        $this->context();
        $this->fakeMetaSuccess('wamid.reply.help.1');

        $payload = $this->payload(
            'wamid.command.help.1',
            self::WA_ID,
            '/ayuda',
        );

        $this->postRaw($payload)
            ->assertOk()
            ->assertJson(['processed' => 1]);

        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseHas('whatsapp_inbound_messages', [
            'message_id' => 'wamid.command.help.1',
            'status' => 'command_processed',
            'confirmation_status' => 'sent',
            'confirmation_message_id' => 'wamid.reply.help.1',
        ]);

        Http::assertSent(fn (Request $request): bool =>
            data_get($request->data(), 'text.body') !== null
            && str_contains(
                (string) data_get($request->data(), 'text.body'),
                '/resumen — estado operativo del ámbito',
            )
        );
    }

    public function test_summary_command_uses_authorized_organization_context_without_writes(): void
    {
        $this->enableWebhook(true, true);
        [$user, $organization] = $this->context();
        $this->fakeMetaSuccess('wamid.reply.summary.1');

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea abierta',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $payload = $this->payload(
            'wamid.command.summary.1',
            self::WA_ID,
            '/resumen',
        );

        $this->postRaw($payload)
            ->assertOk()
            ->assertJson(['processed' => 1]);

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('whatsapp_inbound_messages', [
            'message_id' => 'wamid.command.summary.1',
            'status' => 'command_processed',
            'task_id' => null,
        ]);

        Http::assertSent(fn (Request $request): bool =>
            str_contains(
                (string) data_get($request->data(), 'text.body'),
                'Tareas abiertas: 1',
            )
        );
    }

    public function test_today_command_only_surfaces_current_users_priorities(): void
    {
        $this->enableWebhook(true, true);
        [$user, $organization] = $this->context();
        $other = User::factory()->create();
        $organization->users()->attach($other->id, [
            'role' => 'member',
            'is_default' => false,
            'is_active' => true,
        ]);
        $this->fakeMetaSuccess('wamid.reply.today.1');

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Prioridad propia',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subHour(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Prioridad de otra persona',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subHour(),
            'assigned_to' => $other->id,
            'created_by' => $other->id,
        ]);

        $payload = $this->payload(
            'wamid.command.today.1',
            self::WA_ID,
            '/hoy',
        );

        $this->postRaw($payload)->assertOk();

        Http::assertSent(function (Request $request): bool {
            $body = (string) data_get($request->data(), 'text.body');

            return str_contains($body, 'Prioridad propia')
                && ! str_contains($body, 'Prioridad de otra persona');
        });
    }

    public function test_incidents_command_returns_open_incidents_from_current_scope(): void
    {
        $this->enableWebhook(true, true);
        [$user, $organization] = $this->context();
        $this->fakeMetaSuccess('wamid.reply.incidents.1');

        Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'API principal caída',
            'category' => 'availability',
            'severity' => 'critical',
            'status' => 'new',
            'source' => 'manual',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $payload = $this->payload(
            'wamid.command.incidents.1',
            self::WA_ID,
            '/incidentes',
        );

        $this->postRaw($payload)->assertOk();

        Http::assertSent(fn (Request $request): bool =>
            str_contains(
                (string) data_get($request->data(), 'text.body'),
                '[Crítica] API principal caída',
            )
        );
    }

    public function test_unknown_command_never_becomes_a_task(): void
    {
        $this->enableWebhook(true, true);
        $this->context();
        $this->fakeMetaSuccess('wamid.reply.unknown.1');

        $payload = $this->payload(
            'wamid.command.unknown.1',
            self::WA_ID,
            '/borrar todo',
        );

        $this->postRaw($payload)
            ->assertOk()
            ->assertJson(['processed' => 1]);

        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseHas('whatsapp_inbound_messages', [
            'message_id' => 'wamid.command.unknown.1',
            'status' => 'command_unknown',
            'task_id' => null,
        ]);

        Http::assertSent(fn (Request $request): bool =>
            str_contains(
                (string) data_get($request->data(), 'text.body'),
                'Comando no reconocido.',
            )
        );
    }

    public function test_sender_identity_map_selects_the_mapped_users_scope(): void
    {
        $this->enableWebhook(true, true, [
            self::WA_ID,
            self::SECOND_WA_ID,
        ]);

        $this->context();
        [$mappedUser, $mappedOrganization] = $this->context(
            'mapped@example.com',
            'CLIENTE MAPEADO',
            'cliente-mapeado',
        );

        config()->set('whatsapp.sender_user_map', [
            self::SECOND_WA_ID => $mappedUser->email,
        ]);

        $this->fakeMetaSuccess('wamid.reply.mapped.1');

        $payload = $this->payload(
            'wamid.command.mapped.1',
            self::SECOND_WA_ID,
            '/resumen',
        );

        $this->postRaw($payload)->assertOk();

        Http::assertSent(fn (Request $request): bool =>
            str_contains(
                (string) data_get($request->data(), 'text.body'),
                'CENTRAL · '.$mappedOrganization->name,
            )
        );
    }

    public function test_viewer_can_use_read_only_summary_command(): void
    {
        $this->enableWebhook(true, true);
        [, $organization] = $this->context(
            'rcontreras@arpynet.com',
            'ARPYNET',
            'arpynet',
            'viewer',
        );
        $this->fakeMetaSuccess('wamid.reply.viewer.1');

        $payload = $this->payload(
            'wamid.command.viewer.1',
            self::WA_ID,
            '/resumen',
        );

        $this->postRaw($payload)
            ->assertOk()
            ->assertJson(['processed' => 1]);

        $this->assertDatabaseCount('tasks', 0);

        Http::assertSent(fn (Request $request): bool =>
            str_contains(
                (string) data_get($request->data(), 'text.body'),
                'CENTRAL · '.$organization->name,
            )
        );
    }

    public function test_viewer_cannot_create_task_from_free_text(): void
    {
        $this->enableWebhook(true, false);
        $this->context(
            'rcontreras@arpynet.com',
            'ARPYNET',
            'arpynet',
            'viewer',
        );

        $payload = $this->payload(
            'wamid.viewer.write.1',
            self::WA_ID,
            'Crear una tarea que no debe existir',
        );

        $this->postRaw($payload)
            ->assertStatus(503)
            ->assertJson(['status' => 'unavailable']);

        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('whatsapp_inbound_messages', 0);
    }

    private function enableWebhook(
        bool $commands,
        bool $outbound,
        array $allowed = [self::WA_ID],
    ): void {
        config()->set('whatsapp.enabled', true);
        config()->set('whatsapp.verify_token', self::VERIFY_TOKEN);
        config()->set('whatsapp.app_secret', self::APP_SECRET);
        config()->set('whatsapp.allowed_wa_ids', $allowed);
        config()->set('whatsapp.user_email', 'rcontreras@arpynet.com');
        config()->set('whatsapp.sender_user_map', []);
        config()->set('whatsapp.default_organization_id', null);
        config()->set('whatsapp.commands_enabled', $commands);
        config()->set('whatsapp.outbound_enabled', $outbound);
        config()->set('whatsapp.graph_version', 'v26.0');
        config()->set('whatsapp.access_token', 'test-access-token');
        config()->set('whatsapp.phone_number_id', 'phone-test');
        config()->set('whatsapp.confirm_task_creation', true);
    }

    private function context(
        string $email = 'rcontreras@arpynet.com',
        string $organizationName = 'ARPYNET',
        string $slug = 'arpynet',
        string $role = 'owner',
    ): array {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => $organizationName,
            'slug' => $slug,
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

    private function fakeMetaSuccess(string $messageId): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [[
                    'id' => $messageId,
                ]],
            ], 200),
        ]);
    }

    private function payload(
        string $messageId,
        string $from,
        string $text,
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-test',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'phone-test',
                        ],
                        'messages' => [[
                            'from' => $from,
                            'id' => $messageId,
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => [
                                'body' => $text,
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function postRaw(array $payload)
    {
        $raw = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES,
        );

        $signature = 'sha256='.hash_hmac(
            'sha256',
            $raw,
            self::APP_SECRET,
        );

        return $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $raw,
        );
    }
}
