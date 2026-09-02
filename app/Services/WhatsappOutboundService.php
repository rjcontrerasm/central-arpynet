<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsappOutboundService
{
    /**
     * @return array{
     *     status: 'sent'|'failed'|'skipped',
     *     message_id: ?string,
     *     error_code: ?string
     * }
     */
    public function sendTaskConfirmation(
        string $to,
        string $taskTitle,
        ?string $inboundPhoneNumberId = null,
    ): array {
        if (
            ! config('whatsapp.outbound_enabled')
            || ! config('whatsapp.confirm_task_creation')
        ) {
            return $this->result('skipped');
        }

        $token = trim(
            (string) config(
                'whatsapp.access_token',
                '',
            ),
        );

        $phoneNumberId = trim(
            (string) (
                config('whatsapp.phone_number_id')
                ?: $inboundPhoneNumberId
                ?: ''
            ),
        );

        $version = trim(
            (string) config(
                'whatsapp.graph_version',
                'v26.0',
            ),
        );

        if (
            $token === ''
            || $phoneNumberId === ''
            || $version === ''
        ) {
            return $this->result(
                'failed',
                null,
                'outbound_config_missing',
            );
        }

        $prefix = trim(
            (string) config(
                'whatsapp.confirmation_prefix',
                '✅ Tarea registrada:',
            ),
        );

        $body = trim(
            $prefix.' '.$taskTitle,
        );

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post(
                    'https://graph.facebook.com/'
                    .$version
                    .'/'
                    .$phoneNumberId
                    .'/messages',
                    [
                        'messaging_product' =>
                            'whatsapp',
                        'recipient_type' =>
                            'individual',
                        'to' => $to,
                        'type' => 'text',
                        'text' => [
                            'preview_url' => false,
                            'body' => $body,
                        ],
                    ],
                );
        } catch (Throwable) {
            return $this->result(
                'failed',
                null,
                'transport_error',
            );
        }

        if (! $response->successful()) {
            $metaCode = data_get(
                $response->json(),
                'error.code',
            );

            return $this->result(
                'failed',
                null,
                is_scalar($metaCode)
                    ? 'meta_'.(string) $metaCode
                    : 'http_'.$response->status(),
            );
        }

        $messageId = data_get(
            $response->json(),
            'messages.0.id',
        );

        if (
            ! is_scalar($messageId)
            || trim((string) $messageId) === ''
        ) {
            return $this->result(
                'failed',
                null,
                'missing_message_id',
            );
        }

        return $this->result(
            'sent',
            trim((string) $messageId),
        );
    }

    /**
     * @param array<int, string|int> $bodyParameters
     * @return array{
     *     status: 'sent'|'failed'|'skipped',
     *     message_id: ?string,
     *     error_code: ?string
     * }
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language,
        array $bodyParameters = [],
    ): array {
        if (! config('whatsapp.outbound_enabled')) {
            return $this->result('skipped');
        }

        $token = trim(
            (string) config(
                'whatsapp.access_token',
                '',
            ),
        );

        $phoneNumberId = trim(
            (string) config(
                'whatsapp.phone_number_id',
                '',
            ),
        );

        $version = trim(
            (string) config(
                'whatsapp.graph_version',
                'v26.0',
            ),
        );

        if (
            $token === ''
            || $phoneNumberId === ''
            || $version === ''
            || trim($templateName) === ''
            || trim($language) === ''
        ) {
            return $this->result(
                'failed',
                null,
                'outbound_config_missing',
            );
        }

        $template = [
            'name' => trim($templateName),
            'language' => [
                'code' => trim($language),
            ],
        ];

        if ($bodyParameters !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    static fn (
                        string|int $value,
                    ): array => [
                        'type' => 'text',
                        'text' => (string) $value,
                    ],
                    $bodyParameters,
                ),
            ]];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post(
                    'https://graph.facebook.com/'
                    .$version
                    .'/'
                    .$phoneNumberId
                    .'/messages',
                    [
                        'messaging_product' =>
                            'whatsapp',
                        'recipient_type' =>
                            'individual',
                        'to' => $to,
                        'type' => 'template',
                        'template' => $template,
                    ],
                );
        } catch (Throwable) {
            return $this->result(
                'failed',
                null,
                'transport_error',
            );
        }

        if (! $response->successful()) {
            $metaCode = data_get(
                $response->json(),
                'error.code',
            );

            return $this->result(
                'failed',
                null,
                is_scalar($metaCode)
                    ? 'meta_'.(string) $metaCode
                    : 'http_'.$response->status(),
            );
        }

        $messageId = data_get(
            $response->json(),
            'messages.0.id',
        );

        if (
            ! is_scalar($messageId)
            || trim((string) $messageId) === ''
        ) {
            return $this->result(
                'failed',
                null,
                'missing_message_id',
            );
        }

        return $this->result(
            'sent',
            trim((string) $messageId),
        );
    }

    /**
     * @return array{
     *     status: 'sent'|'failed'|'skipped',
     *     message_id: ?string,
     *     error_code: ?string
     * }
     */
    private function result(
        string $status,
        ?string $messageId = null,
        ?string $errorCode = null,
    ): array {
        return [
            'status' => $status,
            'message_id' => $messageId,
            'error_code' => $errorCode,
        ];
    }
}
