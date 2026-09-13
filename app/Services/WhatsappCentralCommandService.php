<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\CentralAgentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class WhatsappCentralCommandService
{
    public function __construct(
        private readonly CentralAgentGateway $gateway,
    ) {
    }

    public function isCommand(string $text): bool
    {
        return str_starts_with(trim($text), '/');
    }

    /**
     * @return array{
     *     status: 'command_processed'|'command_unknown',
     *     command: string,
     *     purpose: string,
     *     reply: string
     * }
     */
    public function handle(
        User $user,
        Organization $organization,
        string $text,
    ): array {
        $command = $this->command($text);

        return match ($command) {
            'ayuda', 'help' => $this->result(
                'command_processed',
                'ayuda',
                $this->help(),
            ),
            'resumen' => $this->result(
                'command_processed',
                'resumen',
                $this->summary($user, $organization),
            ),
            'hoy' => $this->result(
                'command_processed',
                'hoy',
                $this->today($user, $organization),
            ),
            'incidentes' => $this->result(
                'command_processed',
                'incidentes',
                $this->incidents($user, $organization),
            ),
            default => $this->result(
                'command_unknown',
                $command !== '' ? $command : 'unknown',
                "Comando no reconocido.\n\n".$this->help(),
            ),
        };
    }

    private function summary(
        User $user,
        Organization $organization,
    ): string {
        $context = $this->gateway->organizationContext(
            $user,
            $organization,
        );

        $counts = $context['counts'];
        $attention = collect($context['attention'])
            ->take(3)
            ->map(
                fn (array $item): string =>
                    '• '.Str::limit(
                        (string) $item['title'],
                        90,
                    ).' — '.(string) $item['level_label'],
            )
            ->values();

        $lines = [
            'CENTRAL · '.$organization->name,
            'Resumen operativo',
            '',
            'Tareas abiertas: '.(int) $counts['tasks_open'],
            'Proyectos abiertos: '.(int) $counts['projects_open'],
            'Servicios abiertos: '.(int) $counts['services_open'],
            'Vencimientos pendientes: '.(int) $counts['obligations_pending'],
            'Incidentes abiertos: '.(int) $counts['incidents_open'],
        ];

        if ($attention->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Mayor atención:';
            foreach ($attention as $item) {
                $lines[] = $item;
            }
        }

        return implode("\n", $lines);
    }

    private function today(
        User $user,
        Organization $organization,
    ): string {
        $now = CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $tasks = Task::query()
            ->where('organization_id', $organization->id)
            ->where('assigned_to', $user->id)
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday'],
            )
            ->where(function ($query) use ($now): void {
                $query
                    ->whereIn(
                        'priority_band',
                        ['critical', 'today'],
                    )
                    ->orWhere(
                        'due_at',
                        '<=',
                        $now->endOfDay(),
                    );
            })
            ->orderByDesc('priority_score')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        $lines = [
            'CENTRAL · '.$organization->name,
            'Prioridades de hoy',
            '',
        ];

        if ($tasks->isEmpty()) {
            $lines[] = 'No tienes tareas críticas, vencidas o para hoy en este ámbito.';

            return implode("\n", $lines);
        }

        foreach ($tasks as $index => $task) {
            $label = $task->priority_band === 'critical'
                ? 'CRÍTICA'
                : 'HOY';

            $lines[] = ($index + 1).'. ['.$label.'] '.Str::limit(
                $task->title,
                110,
            );

            if ($task->next_action) {
                $lines[] = '   ↳ '.Str::limit(
                    $task->next_action,
                    120,
                );
            }
        }

        return implode("\n", $lines);
    }

    private function incidents(
        User $user,
        Organization $organization,
    ): string {
        $incidents = Incident::query()
            ->visibleTo($user)
            ->where('organization_id', $organization->id)
            ->open()
            ->orderByRaw(
                "CASE severity\n"
                ."WHEN 'critical' THEN 5\n"
                ."WHEN 'high' THEN 4\n"
                ."WHEN 'medium' THEN 3\n"
                ."WHEN 'low' THEN 2\n"
                ."ELSE 1 END DESC",
            )
            ->orderBy('resolution_due_at')
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get();

        $lines = [
            'CENTRAL · '.$organization->name,
            'Incidentes abiertos',
            '',
        ];

        if ($incidents->isEmpty()) {
            $lines[] = 'No hay incidentes abiertos en este ámbito.';

            return implode("\n", $lines);
        }

        foreach ($incidents as $index => $incident) {
            $severity = Incident::severityOptions()[$incident->severity]
                ?? ucfirst((string) $incident->severity);

            $lines[] = ($index + 1).'. ['.$severity.'] '.Str::limit(
                $incident->title,
                105,
            );
            $lines[] = '   ↳ '.$incident->attention_label;
        }

        return implode("\n", $lines);
    }

    private function help(): string
    {
        return implode("\n", [
            'CENTRAL por WhatsApp',
            '',
            '/resumen — estado operativo del ámbito',
            '/hoy — tus prioridades de hoy',
            '/incidentes — incidentes abiertos',
            '/ayuda — ver estos comandos',
            '',
            'Cualquier texto sin / continúa registrándose como tarea.',
        ]);
    }

    private function command(string $text): string
    {
        $first = preg_split(
            '/\s+/u',
            trim($text),
            2,
        )[0] ?? '';

        return Str::lower(
            ltrim(trim($first), '/'),
        );
    }

    private function result(
        string $status,
        string $command,
        string $reply,
    ): array {
        return [
            'status' => $status,
            'command' => $command,
            'purpose' => 'central_command_'.$command,
            'reply' => Str::limit($reply, 3500, ''),
        ];
    }
}
