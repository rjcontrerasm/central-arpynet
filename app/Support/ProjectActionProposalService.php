<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProjectActionProposalService
{
    private const ACTIONS = [
        'project.status.set' => [
            'label' => 'Cambiar estado del proyecto',
            'risk' => 'state_change',
            'effect' => 'update',
        ],
        'project.next_action.set' => [
            'label' => 'Definir siguiente acción',
            'risk' => 'content_change',
            'effect' => 'update',
        ],
        'project.blockers.clear' => [
            'label' => 'Limpiar bloqueos',
            'risk' => 'content_change',
            'effect' => 'update',
        ],
        'project.task.create' => [
            'label' => 'Crear tarea vinculada',
            'risk' => 'entity_create',
            'effect' => 'create',
        ],
    ];

    public function catalog(): array
    {
        return collect(self::ACTIONS)
            ->map(
                fn (array $definition): array =>
                    $definition + [
                        'confirmation_required' => true,
                    ],
            )
            ->all();
    }

    public function preview(
        User $actor,
        Project $project,
        string $action,
        array $payload = [],
        ?CarbonImmutable $now = null,
    ): array {
        $definition = $this->definition($action);

        $this->authorize(
            $actor,
            $project,
        );

        $now ??= CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        return [
            'action' => $action,
            'label' => $definition['label'],
            'risk' => $definition['risk'],
            'effect' => $definition['effect'],
            'confirmation_required' => true,
            'subject_type' => 'project',
            'subject_id' => $project->id,
            'subject_title' => $project->name,
            'organization_id' =>
                $project->organization_id,
            'proposed_changes' =>
                $this->changesFor(
                    $project,
                    $action,
                    $payload,
                    $now,
                ),
        ];
    }

    private function changesFor(
        Project $project,
        string $action,
        array $payload,
        CarbonImmutable $now,
    ): array {
        return match ($action) {
            'project.status.set' => [
                'status' =>
                    $this->requiredOption(
                        $payload,
                        'status',
                        array_keys(
                            Project::statusOptions(),
                        ),
                    ),
            ],

            'project.next_action.set' => [
                'next_action' =>
                    $this->requiredText(
                        $payload,
                        'next_action',
                        255,
                    ),
            ],

            'project.blockers.clear' => [
                'blockers' => null,
            ],

            'project.task.create' => [
                'task' => [
                    'organization_id' =>
                        $project->organization_id,
                    'project_id' => $project->id,
                    'title' =>
                        $this->requiredText(
                            $payload,
                            'title',
                            255,
                        ),
                    'status' => 'pending',
                    'urgency' =>
                        $this->option(
                            $payload,
                            'urgency',
                            [
                                'low',
                                'normal',
                                'high',
                                'critical',
                            ],
                            'normal',
                        ),
                    'impact' => 'normal',
                    'due_at' =>
                        $this->dueAt(
                            $payload['due_date']
                                ?? null,
                            $now,
                        ),
                    'source' =>
                        'agent_project_preview',
                ],
            ],
        };
    }

    private function dueAt(
        mixed $value,
        CarbonImmutable $now,
    ): ?string {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        try {
            return CarbonImmutable::parse(
                (string) $value,
                $now->timezoneName,
            )
                ->setTime(17, 0)
                ->toIso8601String();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'due_date' =>
                    'La fecha propuesta no es válida.',
            ]);
        }
    }

    private function requiredText(
        array $payload,
        string $key,
        int $max,
    ): string {
        $value = trim(
            (string) ($payload[$key] ?? ''),
        );

        if (
            $value === ''
            || mb_strlen($value) > $max
        ) {
            throw ValidationException::withMessages([
                $key =>
                    "El campo {$key} es obligatorio y debe tener máximo {$max} caracteres.",
            ]);
        }

        return $value;
    }

    private function requiredOption(
        array $payload,
        string $key,
        array $allowed,
    ): string {
        $value = trim(
            (string) ($payload[$key] ?? ''),
        );

        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([
                $key =>
                    "El valor propuesto para {$key} no está permitido.",
            ]);
        }

        return $value;
    }

    private function option(
        array $payload,
        string $key,
        array $allowed,
        string $default,
    ): string {
        $value = trim(
            (string) ($payload[$key] ?? $default),
        );

        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([
                $key =>
                    "El valor propuesto para {$key} no está permitido.",
            ]);
        }

        return $value;
    }

    private function definition(
        string $action,
    ): array {
        if (! isset(self::ACTIONS[$action])) {
            throw new InvalidArgumentException(
                'Acción de proyecto no permitida.',
            );
        }

        return self::ACTIONS[$action];
    }

    private function authorize(
        User $actor,
        Project $project,
    ): void {
        $allowed = DB::table(
            'organization_user',
        )
            ->where('user_id', $actor->id)
            ->where(
                'organization_id',
                $project->organization_id,
            )
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para este proyecto.',
            );
        }
    }
}
