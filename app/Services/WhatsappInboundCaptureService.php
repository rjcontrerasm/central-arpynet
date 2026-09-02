<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappInboundMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsappInboundCaptureService
{
    /**
     * @return 'processed'|'duplicate'|'ignored_sender'|'ignored_type'
     */
    public function capture(
        array $message,
        ?string $phoneNumberId = null,
    ): string {
        $messageId = trim(
            (string) ($message['id'] ?? ''),
        );

        $senderWaId = $this->normalizeWaId(
            (string) ($message['from'] ?? ''),
        );

        $messageType = trim(
            (string) ($message['type'] ?? ''),
        );

        if (
            $messageId === ''
            || $senderWaId === ''
        ) {
            return 'ignored_type';
        }

        if (! $this->senderAllowed($senderWaId)) {
            return 'ignored_sender';
        }

        if (
            WhatsappInboundMessage::query()
                ->where('message_id', $messageId)
                ->exists()
        ) {
            return 'duplicate';
        }

        if ($messageType !== 'text') {
            return $this->recordIgnoredType(
                $messageId,
                $senderWaId,
                $phoneNumberId,
                $messageType,
                $message['timestamp'] ?? null,
            );
        }

        $text = trim(
            (string) data_get(
                $message,
                'text.body',
                '',
            ),
        );

        if ($text === '') {
            return $this->recordIgnoredType(
                $messageId,
                $senderWaId,
                $phoneNumberId,
                $messageType,
                $message['timestamp'] ?? null,
            );
        }

        [$user, $organization] =
            $this->resolveContext();

        try {
            return DB::transaction(
                function () use (
                    $messageId,
                    $senderWaId,
                    $phoneNumberId,
                    $messageType,
                    $message,
                    $text,
                    $user,
                    $organization,
                ): string {
                    $inbound =
                        WhatsappInboundMessage::query()
                            ->create([
                                'message_id' =>
                                    $messageId,
                                'sender_wa_id' =>
                                    $senderWaId,
                                'phone_number_id' =>
                                    $phoneNumberId,
                                'message_type' =>
                                    $messageType,
                                'status' =>
                                    'processing',
                                'text_sha256' =>
                                    hash(
                                        'sha256',
                                        $text,
                                    ),
                                'text_length' =>
                                    mb_strlen($text),
                                'received_at' =>
                                    $this->receivedAt(
                                        $message[
                                            'timestamp'
                                        ] ?? null,
                                    ),
                            ]);

                    $normalizedText =
                        preg_replace(
                            '/\s+/u',
                            ' ',
                            $text,
                        ) ?? $text;

                    $title = Str::limit(
                        trim($normalizedText),
                        255,
                        '',
                    );

                    $task = new Task();

                    $task->forceFill([
                        'organization_id' =>
                            $organization->id,
                        'title' => $title,
                        'description' =>
                            mb_strlen($text) > 255
                                ? $text
                                : null,
                        'status' => 'pending',
                        'urgency' => 'normal',
                        'impact' => 'normal',
                        'source' => 'whatsapp',
                        'external_system' =>
                            'whatsapp_cloud',
                        'external_id' =>
                            $messageId,
                        'assigned_to' =>
                            $user->id,
                        'created_by' =>
                            $user->id,
                    ]);

                    $task->save();

                    $inbound->forceFill([
                        'status' => 'processed',
                        'task_id' => $task->id,
                        'processed_at' => now(),
                    ])->save();

                    return 'processed';
                },
            );
        } catch (QueryException $exception) {
            if (
                (string) $exception->getCode()
                === '23000'
            ) {
                return 'duplicate';
            }

            throw $exception;
        }
    }

    private function recordIgnoredType(
        string $messageId,
        string $senderWaId,
        ?string $phoneNumberId,
        string $messageType,
        mixed $timestamp,
    ): string {
        try {
            WhatsappInboundMessage::query()
                ->create([
                    'message_id' => $messageId,
                    'sender_wa_id' => $senderWaId,
                    'phone_number_id' =>
                        $phoneNumberId,
                    'message_type' =>
                        $messageType !== ''
                            ? $messageType
                            : null,
                    'status' => 'ignored_type',
                    'received_at' =>
                        $this->receivedAt(
                            $timestamp,
                        ),
                    'processed_at' => now(),
                ]);
        } catch (QueryException $exception) {
            if (
                (string) $exception->getCode()
                === '23000'
            ) {
                return 'duplicate';
            }

            throw $exception;
        }

        return 'ignored_type';
    }

    private function senderAllowed(
        string $senderWaId,
    ): bool {
        $allowed = collect(
            config(
                'whatsapp.allowed_wa_ids',
                [],
            ),
        )
            ->map(
                fn (mixed $waId): string =>
                    $this->normalizeWaId(
                        (string) $waId,
                    ),
            )
            ->filter()
            ->values();

        return $allowed->contains(
            $senderWaId,
        );
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function resolveContext(): array
    {
        $email = strtolower(
            trim(
                (string) config(
                    'whatsapp.user_email',
                    '',
                ),
            ),
        );

        if ($email === '') {
            throw new RuntimeException(
                'whatsapp_user_not_configured',
            );
        }

        $user = User::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [$email],
            )
            ->first();

        if (! $user) {
            throw new RuntimeException(
                'whatsapp_user_not_found',
            );
        }

        $membershipIds = DB::table(
            'organization_user',
        )
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        $configuredId = config(
            'whatsapp.default_organization_id',
        );

        $candidates = collect([
            $configuredId,
            $user->current_organization_id,
            DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->where('is_default', true)
                ->value('organization_id'),
            $membershipIds->first(),
        ])
            ->filter(
                fn (mixed $id): bool =>
                    is_numeric($id)
                    && $membershipIds->contains(
                        (int) $id,
                    ),
            )
            ->map(
                fn (mixed $id): int =>
                    (int) $id,
            )
            ->unique()
            ->values();

        foreach ($candidates as $id) {
            $organization =
                Organization::query()
                    ->whereKey($id)
                    ->where('is_active', true)
                    ->first();

            if ($organization) {
                return [$user, $organization];
            }
        }

        throw new RuntimeException(
            'whatsapp_organization_not_found',
        );
    }

    private function normalizeWaId(
        string $value,
    ): string {
        return preg_replace(
            '/\D+/',
            '',
            trim($value),
        ) ?? '';
    }

    private function receivedAt(
        mixed $timestamp,
    ): CarbonImmutable {
        if (
            is_numeric($timestamp)
            && (int) $timestamp > 0
        ) {
            return CarbonImmutable::createFromTimestampUTC(
                (int) $timestamp,
            )->setTimezone(
                config(
                    'app.timezone',
                    'America/Lima',
                ),
            );
        }

        return CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );
    }
}
