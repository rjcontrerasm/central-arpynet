<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExecutiveSummaryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $period,
        private readonly array $counts,
        private readonly string $generatedAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $weekly = $this->period === 'week';

        return [
            'kind' => 'executive_summary',
            'period' => $this->period,
            'title' => $weekly
                ? 'Resumen de próximos 7 días'
                : 'Resumen de hoy',
            'message' => implode(' · ', [
                $this->counts['critical'].' críticos',
                $this->counts['attention'].' a vigilar',
                $this->counts['tasks_due'].' tareas',
                $this->counts['obligations'].' vencimientos',
            ]),
            'url' => route(
                'executive-summary.show',
                ['period' => $this->period],
            ),
            'counts' => $this->counts,
            'generated_at' => $this->generatedAt,
        ];
    }
}
