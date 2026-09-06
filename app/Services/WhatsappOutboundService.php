<?php

namespace App\Services;

use App\Models\WhatsappOutboundAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WhatsappOutboundService
{
    public function sendTaskConfirmation(
        string $to,
        string $taskTitle,
        ?string $inboundPhoneNumberId = null,
    ): array {
        if (! config('whatsapp.outbound_enabled') || ! config('whatsapp.confirm_task_creation')) {
            return $this->result('skipped');
        }

        $transport = $this->transport($inboundPhoneNumberId);

        if (! $transport['ready']) {
            return $this->finalize('task_confirmation', 'text', $to, $this->result(
                'failed', null, 'outbound_config_missing', null, null,
                'configuration', 'Configuración outbound incompleta.'
            ));
        }

        $prefix = trim((string) config('whatsapp.confirmation_prefix', '✅ Tarea registrada:'));

        return $this->sendPayload(
            'task_confirmation',
            'text',
            $to,
            $transport,
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => trim($prefix.' '.$taskTitle),
                ],
            ],
        );
    }

    public function sendTemplate(
        string $to,
        string $templateName,
        string $language,
        array $bodyParameters = [],
        string $purpose = 'template',
    ): array {
        if (! config('whatsapp.outbound_enabled')) {
            return $this->result('skipped');
        }

        $transport = $this->transport();

        if (! $transport['ready'] || trim($templateName) === '' || trim($language) === '') {
            return $this->finalize($purpose, 'template', $to, $this->result(
                'failed', null, 'outbound_config_missing', null, null,
                'configuration', 'Configuración outbound o template incompleta.'
            ));
        }

        $template = [
            'name' => trim($templateName),
            'language' => ['code' => trim($language)],
        ];

        if ($bodyParameters !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string|int $value): array => [
                        'type' => 'text',
                        'text' => (string) $value,
                    ],
                    $bodyParameters,
                ),
            ]];
        }

        return $this->sendPayload(
            $purpose,
            'template',
            $to,
            $transport,
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => $template,
            ],
        );
    }

    private function sendPayload(
        string $purpose,
        string $requestKind,
        string $to,
        array $transport,
        array $payload,
    ): array {
        try {
            $response = Http::withToken($transport['token'])
                ->acceptJson()
                ->timeout(15)
                ->post(
                    'https://graph.facebook.com/'.$transport['version'].'/'.$transport['phone_number_id'].'/messages',
                    $payload,
                );
        } catch (Throwable $exception) {
            return $this->finalize($purpose, $requestKind, $to, $this->result(
                'failed', null, 'transport_error', null, null,
                class_basename($exception), 'No se pudo conectar con Meta Graph API.'
            ));
        }

        if (! $response->successful()) {
            $metaCode = data_get($response->json(), 'error.code');
            $subcode = data_get($response->json(), 'error.error_subcode');

            return $this->finalize($purpose, $requestKind, $to, $this->result(
                'failed',
                null,
                is_scalar($metaCode) ? 'meta_'.(string) $metaCode : 'http_'.$response->status(),
                is_scalar($subcode) ? (string) $subcode : null,
                $response->status(),
                $this->diagnosticText(data_get($response->json(), 'error.type'), 120),
                $this->diagnosticMessage(data_get($response->json(), 'error.message')),
                $this->diagnosticText(data_get($response->json(), 'error.fbtrace_id'), 120),
            ));
        }

        $messageId = data_get($response->json(), 'messages.0.id');

        if (! is_scalar($messageId) || trim((string) $messageId) === '') {
            return $this->finalize($purpose, $requestKind, $to, $this->result(
                'failed', null, 'missing_message_id', null, $response->status(),
                'protocol', 'Meta respondió sin identificador de mensaje.'
            ));
        }

        return $this->finalize($purpose, $requestKind, $to, $this->result(
            'sent', trim((string) $messageId), null, null, $response->status()
        ));
    }

    private function transport(?string $fallbackPhoneNumberId = null): array
    {
        $token = trim((string) config('whatsapp.access_token', ''));
        $phoneNumberId = trim((string) (config('whatsapp.phone_number_id') ?: $fallbackPhoneNumberId ?: ''));
        $version = trim((string) config('whatsapp.graph_version', 'v26.0'));

        return [
            'ready' => $token !== '' && $phoneNumberId !== '' && $version !== '',
            'token' => $token,
            'phone_number_id' => $phoneNumberId,
            'version' => $version,
        ];
    }

    private function finalize(
        string $purpose,
        string $requestKind,
        string $to,
        array $result,
    ): array {
        $attemptId = null;

        try {
            $attempt = WhatsappOutboundAttempt::query()->create([
                'purpose' => Str::limit(Str::snake(trim($purpose) !== '' ? trim($purpose) : 'unknown'), 40, ''),
                'request_kind' => Str::limit(trim($requestKind), 20, ''),
                'recipient_sha256' => hash('sha256', trim($to)),
                'status' => $result['status'],
                'message_id' => $result['message_id'],
                'error_code' => $result['error_code'],
                'error_subcode' => $result['error_subcode'],
                'http_status' => $result['http_status'],
                'error_type' => $result['error_type'],
                'error_message' => $result['error_message'],
                'fbtrace_id' => $result['fbtrace_id'],
            ]);

            $attemptId = $attempt->id;
        } catch (Throwable) {
            Log::warning('WhatsApp outbound diagnostic persistence failed.', [
                'purpose' => Str::limit(Str::snake($purpose), 40, ''),
                'error_code' => 'diagnostic_persistence_failed',
            ]);
        }

        $result['attempt_id'] = $attemptId;

        return $result;
    }

    private function diagnosticMessage(mixed $value): ?string
    {
        $message = $this->diagnosticText($value, 500);

        if ($message === null) {
            return null;
        }

        $message = preg_replace('/Bearer\s+\S+/iu', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/\bEAA[A-Za-z0-9_-]{8,}\b/u', '[redacted-token]', $message) ?? $message;
        $message = preg_replace('/\+?\d{6,}/u', '[redacted-number]', $message) ?? $message;

        return Str::limit($message, 500, '');
    }

    private function diagnosticText(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    private function result(
        string $status,
        ?string $messageId = null,
        ?string $errorCode = null,
        ?string $errorSubcode = null,
        ?int $httpStatus = null,
        ?string $errorType = null,
        ?string $errorMessage = null,
        ?string $fbtraceId = null,
    ): array {
        return [
            'status' => $status,
            'message_id' => $messageId,
            'error_code' => $errorCode,
            'error_subcode' => $errorSubcode,
            'http_status' => $httpStatus,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'fbtrace_id' => $fbtraceId,
            'attempt_id' => null,
        ];
    }
}
