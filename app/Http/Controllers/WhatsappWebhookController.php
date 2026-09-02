<?php

namespace App\Http\Controllers;

use App\Services\WhatsappInboundCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class WhatsappWebhookController extends Controller
{
    public function verify(
        Request $request,
    ): Response {
        abort_unless(
            config('whatsapp.enabled'),
            404,
        );

        $mode =
            $request->query('hub.mode')
            ?? $request->query('hub_mode');

        $token =
            $request->query('hub.verify_token')
            ?? $request->query(
                'hub_verify_token',
            );

        $challenge =
            $request->query('hub.challenge')
            ?? $request->query(
                'hub_challenge',
            );

        $expected = (string) config(
            'whatsapp.verify_token',
            '',
        );

        abort_if($expected === '', 503);

        $valid =
            (string) $mode === 'subscribe'
            && is_string($token)
            && hash_equals(
                $expected,
                $token,
            );

        abort_unless($valid, 403);

        return response(
            (string) $challenge,
            200,
            [
                'Content-Type' =>
                    'text/plain; charset=UTF-8',
            ],
        );
    }

    public function receive(
        Request $request,
        WhatsappInboundCaptureService $capture,
    ): JsonResponse {
        abort_unless(
            config('whatsapp.enabled'),
            404,
        );

        $secret = (string) config(
            'whatsapp.app_secret',
            '',
        );

        abort_if($secret === '', 503);

        $rawBody = $request->getContent();

        $signature = strtolower(
            trim(
                (string) $request->header(
                    'X-Hub-Signature-256',
                    '',
                ),
            ),
        );

        $expected =
            'sha256='
            .hash_hmac(
                'sha256',
                $rawBody,
                $secret,
            );

        abort_unless(
            $signature !== ''
            && hash_equals(
                $expected,
                $signature,
            ),
            401,
        );

        try {
            $payload = json_decode(
                $rawBody,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return response()->json(
                ['status' => 'invalid_json'],
                400,
            );
        }

        if (! is_array($payload)) {
            return response()->json(
                ['status' => 'invalid_payload'],
                400,
            );
        }

        $counts = [
            'processed' => 0,
            'duplicate' => 0,
            'ignored_sender' => 0,
            'ignored_type' => 0,
        ];

        try {
            foreach (
                $this->messages($payload)
                as $item
            ) {
                $result = $capture->capture(
                    $item['message'],
                    $item['phone_number_id'],
                );

                $counts[$result]++;
            }
        } catch (RuntimeException $exception) {
            Log::error(
                'WhatsApp inbound configuration error.',
                [
                    'error_code' =>
                        $exception->getMessage(),
                ],
            );

            return response()->json(
                ['status' => 'unavailable'],
                503,
            );
        }

        return response()->json([
            'status' => 'ok',
            ...$counts,
        ]);
    }

    /**
     * @return array<int, array{
     *     message: array,
     *     phone_number_id: ?string
     * }>
     */
    private function messages(
        array $payload,
    ): array {
        $result = [];

        foreach (
            $payload['entry'] ?? []
            as $entry
        ) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (
                $entry['changes'] ?? []
                as $change
            ) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value']
                    ?? [];

                if (! is_array($value)) {
                    continue;
                }

                $phoneNumberId =
                    data_get(
                        $value,
                        'metadata.phone_number_id',
                    );

                foreach (
                    $value['messages'] ?? []
                    as $message
                ) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $result[] = [
                        'message' => $message,
                        'phone_number_id' =>
                            is_scalar($phoneNumberId)
                                ? (string) $phoneNumberId
                                : null,
                    ];
                }
            }
        }

        return $result;
    }
}
