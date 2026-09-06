<?php

namespace Tests\Feature;

use App\Services\WhatsappOutboundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOutboundDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_failure_is_stored_safely(): void
    {
        $this->configureOutbound();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Recipient 51999999999 failed for account 123456789012.',
                    'type' => 'OAuthException',
                    'code' => 131000,
                    'error_subcode' => 2494010,
                    'fbtrace_id' => 'TRACE-131000',
                ],
            ], 400),
        ]);

        $result = app(WhatsappOutboundService::class)->sendTemplate(
            '51999999999', 'central_test', 'es_PE', ['Prueba'], 'executive_summary'
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame('meta_131000', $result['error_code']);
        $this->assertSame('2494010', $result['error_subcode']);
        $this->assertSame(400, $result['http_status']);
        $this->assertNotNull($result['attempt_id']);

        $attempt = DB::table('whatsapp_outbound_attempts')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('executive_summary', $attempt->purpose);
        $this->assertSame(hash('sha256', '51999999999'), $attempt->recipient_sha256);
        $this->assertSame('meta_131000', $attempt->error_code);
        $this->assertSame('2494010', $attempt->error_subcode);
        $this->assertSame(400, $attempt->http_status);
        $this->assertSame('OAuthException', $attempt->error_type);
        $this->assertSame('TRACE-131000', $attempt->fbtrace_id);
        $this->assertStringNotContainsString('51999999999', (string) $attempt->error_message);
        $this->assertStringNotContainsString('123456789012', (string) $attempt->error_message);
        $this->assertStringContainsString('[redacted-number]', (string) $attempt->error_message);
    }

    public function test_success_is_recorded_without_payload_or_recipient(): void
    {
        $this->configureOutbound();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.safe.1']],
            ], 200),
        ]);

        $result = app(WhatsappOutboundService::class)->sendTemplate(
            '51911111111', 'central_test', 'es_PE', [], 'critical_alert'
        );

        $this->assertSame('sent', $result['status']);
        $this->assertDatabaseHas('whatsapp_outbound_attempts', [
            'purpose' => 'critical_alert',
            'status' => 'sent',
            'message_id' => 'wamid.safe.1',
            'http_status' => 200,
        ]);

        $columns = Schema::getColumnListing('whatsapp_outbound_attempts');
        foreach (['recipient', 'to', 'phone', 'phone_number_id', 'payload', 'raw_payload', 'access_token', 'authorization'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
        $this->assertContains('recipient_sha256', $columns);
    }

    public function test_diagnostic_command_never_prints_secrets_or_recipient(): void
    {
        $this->configureOutbound();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Recipient 51988888888 unavailable.',
                    'type' => 'OAuthException',
                    'code' => 131000,
                    'fbtrace_id' => 'TRACE-SAFE',
                ],
            ], 400),
        ]);

        app(WhatsappOutboundService::class)->sendTemplate(
            '51988888888', 'central_test', 'es_PE', [], 'executive_summary'
        );

        Artisan::call('whatsapp:diagnostics');
        $output = Artisan::output();

        $this->assertStringContainsString('WHATSAPP_DIAGNOSTICS=PASS', $output);
        $this->assertStringContainsString('LAST_FAILURE_ERROR_CODE=meta_131000', $output);
        $this->assertStringContainsString('RECIPIENT_VALUES_EXPOSED=NO', $output);
        $this->assertStringNotContainsString('51988888888', $output);
        $this->assertStringNotContainsString('test-access-token-value', $output);
        $this->assertStringNotContainsString('phone-test-value', $output);
    }

    private function configureOutbound(): void
    {
        config()->set('whatsapp.outbound_enabled', true);
        config()->set('whatsapp.confirm_task_creation', true);
        config()->set('whatsapp.access_token', 'test-access-token-value');
        config()->set('whatsapp.phone_number_id', 'phone-test-value');
        config()->set('whatsapp.graph_version', 'v26.0');
    }
}
