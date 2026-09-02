<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ExecutiveSummaryMail extends Mailable
{
    public function __construct(
        public readonly string $period,
        public readonly array $counts,
        public readonly string $generatedAt,
        public readonly string $summaryUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        $label = $this->period === 'week'
            ? 'Próximos 7 días'
            : 'Resumen de hoy';

        $date = now()
            ->timezone(
                config(
                    'app.timezone',
                    'America/Lima',
                ),
            )
            ->format('d/m/Y');

        return new Envelope(
            from: new Address(
                config(
                    'central.summary_mail.from_address',
                    'notificaciones@central.arpynet.com',
                ),
                config(
                    'central.summary_mail.from_name',
                    'Central ARPYNET',
                ),
            ),
            subject:
                "Central ARPYNET · {$label} · {$date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.executive-summary',
            with: [
                'period' => $this->period,
                'counts' => $this->counts,
                'generatedAt' => $this->generatedAt,
                'summaryUrl' => $this->summaryUrl,
            ],
        );
    }
}
