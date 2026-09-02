<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappInboundCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET =
        'test-app-secret';

    private const VERIFY_TOKEN =
        'test-verify-token';

    private const WA_ID =
        '51999999999';

    public function test_webhook_is_disabled_by_default(): void
    {
        config()->set(
            'whatsapp.enabled',
            false,
        );

        $this->get(
            '/api/webhooks/whatsapp'
            .'?hub_mode=subscribe'
            .'&hub_verify_token=x'
            .'&hub_challenge=123',
        )->assertNotFound();
    }

    public function test_meta_can_verify_callback_url(): void
    {
        $this->enableWebhook();

        $this->get(
            '/api/webhooks/whatsapp'
            .'?hub_mode=subscribe'
            .'&hub_verify_token='
            .self::VERIFY_TOKEN
            .'&hub_challenge=abc123',
        )
            ->assertOk()
            ->assertSeeText('abc123');
    }

    public function test_invalid_verify_token_is_rejected(): void
    {
        $this->enableWebhook();

        $this->get(
            '/api/webhooks/whatsapp'
            .'?hub_mode=subscribe'
            .'&hub_verify_token=wrong'
            .'&hub_challenge=abc123',
        )->assertForbidden();
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->enableWebhook();
        $this->context();

        $payload = $this->payload(
            'wamid.invalid',
            self::WA_ID,
            'Tarea por WhatsApp',
        );

        $this->postRaw(
            $payload,
            'sha256=invalid',
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'tasks',
            0,
        );
    }

    public function test_allowed_text_message_creates_task(): void
    {
        $this->enableWebhook();

        [$user, $organization] =
            $this->context();

        $messageId = 'wamid.task.1';

        $payload = $this->payload(
            $messageId,
            self::WA_ID,
            'Enviar propuesta al cliente',
        );

        $this->postRaw(
            $payload,
            $this->signature($payload),
        )
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'processed' => 1,
            ]);

        $this->assertDatabaseHas(
            'tasks',
            [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Enviar propuesta al cliente',
                'status' => 'pending',
                'urgency' => 'normal',
                'impact' => 'normal',
                'source' => 'whatsapp',
                'external_system' =>
                    'whatsapp_cloud',
                'external_id' =>
                    $messageId,
                'created_by' =>
                    $user->id,
                'assigned_to' =>
                    $user->id,
            ],
        );

        $this->assertDatabaseHas(
            'whatsapp_inbound_messages',
            [
                'message_id' => $messageId,
                'sender_wa_id' =>
                    self::WA_ID,
                'status' => 'processed',
            ],
        );
    }

    public function test_duplicate_message_is_idempotent(): void
    {
        $this->enableWebhook();
        $this->context();

        $payload = $this->payload(
            'wamid.duplicate.1',
            self::WA_ID,
            'Una sola tarea',
        );

        $signature =
            $this->signature($payload);

        $this->postRaw(
            $payload,
            $signature,
        )->assertOk();

        $this->postRaw(
            $payload,
            $signature,
        )
            ->assertOk()
            ->assertJson([
                'duplicate' => 1,
            ]);

        $this->assertDatabaseCount(
            'tasks',
            1,
        );

        $this->assertDatabaseCount(
            'whatsapp_inbound_messages',
            1,
        );
    }

    public function test_unapproved_sender_is_ignored(): void
    {
        $this->enableWebhook();
        $this->context();

        $payload = $this->payload(
            'wamid.foreign.1',
            '51911111111',
            'No crear esta tarea',
        );

        $this->postRaw(
            $payload,
            $this->signature($payload),
        )
            ->assertOk()
            ->assertJson([
                'ignored_sender' => 1,
            ]);

        $this->assertDatabaseCount(
            'tasks',
            0,
        );

        $this->assertDatabaseCount(
            'whatsapp_inbound_messages',
            0,
        );
    }

    public function test_non_text_message_is_recorded_but_not_created(): void
    {
        $this->enableWebhook();
        $this->context();

        $payload = [
            'object' =>
                'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-test',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' =>
                                'phone-test',
                        ],
                        'messages' => [[
                            'from' => self::WA_ID,
                            'id' => 'wamid.image.1',
                            'timestamp' =>
                                (string) time(),
                            'type' => 'image',
                            'image' => [
                                'id' => 'media-test',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postRaw(
            $payload,
            $this->signature($payload),
        )
            ->assertOk()
            ->assertJson([
                'ignored_type' => 1,
            ]);

        $this->assertDatabaseCount(
            'tasks',
            0,
        );

        $this->assertDatabaseHas(
            'whatsapp_inbound_messages',
            [
                'message_id' =>
                    'wamid.image.1',
                'status' => 'ignored_type',
            ],
        );
    }

    private function enableWebhook(): void
    {
        config()->set(
            'whatsapp.enabled',
            true,
        );

        config()->set(
            'whatsapp.verify_token',
            self::VERIFY_TOKEN,
        );

        config()->set(
            'whatsapp.app_secret',
            self::APP_SECRET,
        );

        config()->set(
            'whatsapp.allowed_wa_ids',
            [self::WA_ID],
        );

        config()->set(
            'whatsapp.user_email',
            'rcontreras@arpynet.com',
        );

        config()->set(
            'whatsapp.default_organization_id',
            null,
        );
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

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        return [$user, $organization];
    }

    private function payload(
        string $messageId,
        string $from,
        string $text,
    ): array {
        return [
            'object' =>
                'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-test',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'display_phone_number' =>
                                '15550000000',
                            'phone_number_id' =>
                                'phone-test',
                        ],
                        'messages' => [[
                            'from' => $from,
                            'id' => $messageId,
                            'timestamp' =>
                                (string) time(),
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

    private function signature(
        array $payload,
    ): string {
        $raw = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES,
        );

        return 'sha256='
            .hash_hmac(
                'sha256',
                $raw,
                self::APP_SECRET,
            );
    }

    private function postRaw(
        array $payload,
        string $signature,
    ) {
        $raw = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES,
        );

        return $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' =>
                    'application/json',
                'HTTP_X_HUB_SIGNATURE_256' =>
                    $signature,
            ],
            $raw,
        );
    }
}
