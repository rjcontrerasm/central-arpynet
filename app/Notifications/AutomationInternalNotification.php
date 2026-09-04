<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AutomationInternalNotification
    extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function via(
        object $notifiable,
    ): array {
        return ['database'];
    }

    public function toArray(
        object $notifiable,
    ): array {
        return [
            'type' =>
                'automation_internal',
            'title' =>
                $this->payload['title'],
            'message' =>
                $this->payload['message'],
            'rule_id' =>
                $this->payload['rule_id'],
            'action_key' =>
                $this->payload[
                    'action_key'
                ],
            'subject_type' =>
                $this->payload[
                    'subject_type'
                ],
            'subject_id' =>
                $this->payload[
                    'subject_id'
                ],
            'url' =>
                $this->targetUrl(
                    $this->payload[
                        'action_key'
                    ],
                ),
        ];
    }

    private function targetUrl(
        string $actionKey,
    ): string {
        return match ($actionKey) {
            'service.create_billing_reminder',
            'service.create_collection_reminder' =>
                route(
                    'service-orders-ops.show',
                    [],
                    false,
                ),

            'obligation.create_alert' =>
                route(
                    'obligation-ops.show',
                    [],
                    false,
                ),

            default =>
                route(
                    'daily-ops.show',
                    [],
                    false,
                ),
        };
    }
}
