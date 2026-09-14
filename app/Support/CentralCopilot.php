<?php

namespace App\Support;

use Illuminate\Support\Str;

class CentralCopilot
{
    /**
     * Build a bounded, deterministic answer from context CENTRAL already knows.
     *
     * This layer never performs network requests and never writes data. It is
     * intentionally a closed intent router over verified operational context.
     *
     * @param  array<string, mixed>|null  $focusContext
     * @param  array<string, mixed>  $operationalIntelligence
     * @param  array<string, mixed>  $executivePrioritization
     * @param  array<string, mixed>  $dailyPlan
     * @param  array<string, mixed>  $dailyReviewAssistant
     * @param  array<string, int>  $proposalSummary
     * @return array<string, mixed>
     */
    public function answer(
        ?string $query,
        ?array $focusContext,
        array $operationalIntelligence,
        array $executivePrioritization,
        array $dailyPlan,
        array $dailyReviewAssistant,
        array $proposalSummary,
        ?string $focusOrganizationName,
    ): array {
        $rawQuery = trim((string) $query);
        $normalized = $this->normalize($rawQuery);

        if ($normalized === '') {
            return $this->welcome($focusOrganizationName);
        }

        if ($this->containsAny($normalized, [
            'ayuda',
            'que puedes hacer',
            'como puedes ayudar',
            'capacidades',
            'comandos',
        ])) {
            return $this->capabilities();
        }

        if ($this->containsAny($normalized, [
            'hoy',
            'ahora',
            'primero',
            'prioridad',
            'prioridades',
            'que hago',
            'que debo hacer',
        ])) {
            return $this->today($dailyPlan);
        }

        if ($this->containsAny($normalized, [
            'critico',
            'criticos',
            'riesgo',
            'riesgos',
            'urgente',
            'urgentes',
            'preocupa',
            'presion',
        ])) {
            return $this->risks(
                $operationalIntelligence,
                $executivePrioritization,
                $focusOrganizationName,
            );
        }

        if ($this->containsAny($normalized, [
            'decision',
            'decisiones',
            'propuesta',
            'propuestas',
            'aprobar',
            'aprobacion',
            'delegar',
        ])) {
            return $this->proposals($proposalSummary);
        }

        if ($this->containsAny($normalized, [
            'revision',
            'cierre',
            'cerrar dia',
            'cabos sueltos',
            'pendiente antes de cerrar',
        ])) {
            return $this->review($dailyReviewAssistant);
        }

        if ($this->containsAny($normalized, [
            'estado',
            'panorama',
            'resumen',
            'como estamos',
            'como esta',
            'salud',
        ])) {
            return $this->status(
                $focusContext,
                $operationalIntelligence,
                $focusOrganizationName,
            );
        }

        return [
            'intent' => 'unsupported',
            'query' => $rawQuery,
            'title' => 'No puedo responder eso con evidencia suficiente',
            'answer' => 'Copilot solo responde preguntas operativas que puede resolver con datos verificables de CENTRAL. No inventará una respuesta ni consultará una red externa.',
            'items' => [],
            'suggestions' => $this->suggestions(),
            'confidence' => 'bounded',
            'read_only' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function welcome(?string $organizationName): array
    {
        return [
            'intent' => 'welcome',
            'query' => null,
            'title' => 'CENTRAL Copilot',
            'answer' => $organizationName
                ? "Estoy listo para leer el contexto operativo de {$organizationName} y el panorama transversal de tus ámbitos."
                : 'Estoy listo para leer el contexto operativo disponible en CENTRAL.',
            'items' => [],
            'suggestions' => $this->suggestions(),
            'confidence' => 'deterministic',
            'read_only' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function capabilities(): array
    {
        return [
            'intent' => 'capabilities',
            'query' => null,
            'title' => 'Qué puede hacer Copilot',
            'answer' => 'Puede resumir tu jornada, señalar riesgos, explicar el estado operativo, mostrar cabos sueltos y llevarte a la cola segura de propuestas. Las preguntas son de solo lectura; los cambios siguen pasando por las capas de propuesta, aprobación, confirmación y undo de CENTRAL.',
            'items' => [],
            'suggestions' => $this->suggestions(),
            'confidence' => 'deterministic',
            'read_only' => true,
        ];
    }

    /** @param array<string, mixed> $dailyPlan */
    private function today(array $dailyPlan): array
    {
        $items = [];

        foreach (($dailyPlan['sections'] ?? []) as $section) {
            foreach (($section['items'] ?? []) as $item) {
                $items[] = [
                    'title' => (string) ($item['title'] ?? 'Asunto operativo'),
                    'meta' => trim(implode(' · ', array_filter([
                        $item['organization'] ?? null,
                        $item['type_label'] ?? null,
                        $item['suggested_move'] ?? null,
                    ]))),
                    'url' => $item['url'] ?? null,
                ];

                if (count($items) >= 6) {
                    break 2;
                }
            }
        }

        return [
            'intent' => 'today',
            'query' => null,
            'title' => 'Qué atender hoy',
            'answer' => (string) ($dailyPlan['summary'] ?? 'No hay un plan diario disponible.'),
            'items' => $items,
            'suggestions' => ['¿Qué es crítico?', '¿Cómo estamos?', '¿Qué debo revisar antes de cerrar?'],
            'confidence' => 'deterministic',
            'read_only' => true,
        ];
    }

    /**
     * @param array<string, mixed> $intelligence
     * @param array<string, mixed> $executive
     * @return array<string, mixed>
     */
    private function risks(array $intelligence, array $executive, ?string $organizationName): array
    {
        $items = [];

        foreach (array_slice($executive['top_priorities'] ?? [], 0, 6) as $priority) {
            $items[] = [
                'title' => (string) ($priority['title'] ?? 'Prioridad'),
                'meta' => trim(implode(' · ', array_filter([
                    $priority['organization'] ?? null,
                    $priority['level_label'] ?? null,
                    $priority['why'] ?? null,
                ]))),
                'url' => $priority['url'] ?? null,
            ];
        }

        $pressure = $intelligence['pressure_label'] ?? null;
        $score = $intelligence['pressure_score'] ?? null;
        $scope = $organizationName ? " en {$organizationName}" : '';

        $answer = $intelligence['summary'] ?? 'No hay señales críticas suficientes para construir una lectura de riesgo.';

        if ($pressure !== null && $score !== null) {
            $answer = "Presión {$pressure} ({$score}){$scope}. {$answer}";
        }

        return [
            'intent' => 'risks',
            'query' => null,
            'title' => 'Riesgos y focos críticos',
            'answer' => (string) $answer,
            'items' => $items,
            'suggestions' => ['¿Qué hago primero?', '¿Qué decisiones tengo?', 'Dame el panorama operativo'],
            'confidence' => 'deterministic',
            'read_only' => true,
        ];
    }

    /** @param array<string, int> $summary */
    private function proposals(array $summary): array
    {
        $pending = (int) ($summary['pending'] ?? 0);
        $approved = (int) ($summary['approved'] ?? 0);
        $stale = (int) ($summary['stale'] ?? 0);
        $executed = (int) ($summary['executed'] ?? 0);

        return [
            'intent' => 'proposals',
            'query' => null,
            'title' => 'Decisiones y propuestas',
            'answer' => "Hay {$pending} propuestas pendientes, {$approved} aprobadas esperando ejecución, {$stale} desactualizadas y {$executed} ejecutadas en el filtro autorizado actual.",
            'items' => [],
            'suggestions' => ['¿Qué es crítico?', '¿Qué hago hoy?', '¿Qué puedo preguntarte?'],
            'confidence' => 'deterministic',
            'read_only' => true,
            'cta' => [
                'label' => 'Abrir cola segura de propuestas',
                'route' => 'agent-proposals.index',
            ],
        ];
    }

    /** @param array<string, mixed> $review */
    private function review(array $review): array
    {
        $items = [];

        foreach (($review['sections'] ?? []) as $section) {
            foreach (($section['items'] ?? []) as $item) {
                $items[] = [
                    'title' => (string) ($item['title'] ?? 'Cabo suelto'),
                    'meta' => trim(implode(' · ', array_filter([
                        $item['organization'] ?? null,
                        $item['type_label'] ?? null,
                        $item['suggested_move'] ?? null,
                    ]))),
                    'url' => $item['url'] ?? null,
                ];

                if (count($items) >= 6) {
                    break 2;
                }
            }
        }

        return [
            'intent' => 'review',
            'query' => null,
            'title' => 'Antes de cerrar el día',
            'answer' => (string) ($review['summary'] ?? 'No hay una revisión asistida disponible.'),
            'items' => $items,
            'suggestions' => ['¿Qué hago primero?', '¿Qué es crítico?', '¿Cómo estamos?'],
            'confidence' => 'deterministic',
            'read_only' => true,
            'cta' => [
                'label' => 'Abrir revisión diaria',
                'route' => 'daily-review.show',
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $context
     * @param array<string, mixed> $intelligence
     * @return array<string, mixed>
     */
    private function status(?array $context, array $intelligence, ?string $organizationName): array
    {
        if (! $context) {
            return [
                'intent' => 'status',
                'query' => null,
                'title' => 'Panorama operativo',
                'answer' => 'No hay un ámbito operativo disponible para construir el panorama.',
                'items' => [],
                'suggestions' => $this->suggestions(),
                'confidence' => 'deterministic',
                'read_only' => true,
            ];
        }

        $counts = $context['counts'] ?? [];
        $name = $organizationName ?? ($context['name'] ?? 'el ámbito seleccionado');

        $answer = sprintf(
            '%s: %d tareas abiertas, %d proyectos abiertos, %d servicios abiertos, %d vencimientos pendientes y %d incidentes abiertos. %s',
            $name,
            (int) ($counts['tasks_open'] ?? 0),
            (int) ($counts['projects_open'] ?? 0),
            (int) ($counts['services_open'] ?? 0),
            (int) ($counts['obligations_pending'] ?? 0),
            (int) ($counts['incidents_open'] ?? 0),
            (string) ($intelligence['summary'] ?? ''),
        );

        return [
            'intent' => 'status',
            'query' => null,
            'title' => 'Panorama operativo',
            'answer' => trim($answer),
            'items' => [],
            'suggestions' => ['¿Qué es crítico?', '¿Qué hago hoy?', '¿Qué debo revisar antes de cerrar?'],
            'confidence' => 'deterministic',
            'read_only' => true,
        ];
    }

    /** @return list<string> */
    private function suggestions(): array
    {
        return [
            '¿Qué hago hoy?',
            '¿Qué es crítico?',
            '¿Cómo estamos?',
            '¿Qué decisiones tengo?',
            '¿Qué debo revisar antes de cerrar?',
        ];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    /** @param list<string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
