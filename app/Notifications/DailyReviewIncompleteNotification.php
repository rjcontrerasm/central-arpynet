<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DailyReviewIncompleteNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $reviewDate,
        private readonly int $reviewedCount,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'daily_review_incomplete',
            'title' => 'Revisión diaria pendiente',
            'message' => sprintf(
                'Tu revisión sigue incompleta: %d/4 bloques revisados.',
                $this->reviewedCount,
            ),
            'review_date' => $this->reviewDate,
            'url' => route(
                'daily-review.show',
            ),
        ];
    }
}
