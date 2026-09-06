<?php

namespace App\Support;

use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ServiceOrderActionProposalService
{
    private const ACTIONS = [
        'service_order.stage.set' => [
            'label' => 'Cambiar etapa del servicio',
            'risk' => 'state_change',
            'effect' => 'update',
        ],
        'service_order.next_action.set' => [
            'label' => 'Definir siguiente acción del servicio',
            'risk' => 'content_change',
            'effect' => 'update',
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
        ServiceOrder $order,
        string $action,
        array $payload = [],
        ?CarbonImmutable $now = null,
    ): array {
        $definition = $this->definition($action);

        $this->authorize(
            $actor,
            $order,
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
            'subject_type' => 'service_order',
            'subject_id' => $order->id,
            'subject_title' => $order->title,
            'organization_id' =>
                $order->organization_id,
            'proposed_changes' =>
                $this->changesFor(
                    $action,
                    $payload,
                    $now,
                ),
        ];
    }

    private function changesFor(
        string $action,
        array $payload,
        CarbonImmutable $now,
    ): array {
        return match ($action) {
            'service_order.stage.set' => [
                'stage' =>
                    $this->requiredOption(
                        $payload,
                        'stage',
                        array_keys(
                            ServiceOrder::stageOptions(),
                        ),
                    ),
            ],

            'service_order.next_action.set' => [
                'next_action' =>
                    $this->requiredText(
                        $payload,
                        'next_action',
                        255,
                    ),
                'next_action_at' =>
                    $this->dateTime(
                        $payload['next_action_at']
                            ?? null,
                        $now,
                    ),
            ],
        };
    }

    private function dateTime(
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
            )->toIso8601String();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'next_action_at' =>
                    'La fecha/hora propuesta no es válida.',
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

    private function definition(
        string $action,
    ): array {
        if (! isset(self::ACTIONS[$action])) {
            throw new InvalidArgumentException(
                'Acción de servicio no permitida.',
            );
        }

        return self::ACTIONS[$action];
    }

    private function authorize(
        User $actor,
        ServiceOrder $order,
    ): void {
        $allowed = DB::table(
            'organization_user',
        )
            ->where('user_id', $actor->id)
            ->where(
                'organization_id',
                $order->organization_id,
            )
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para este servicio.',
            );
        }
    }
}
