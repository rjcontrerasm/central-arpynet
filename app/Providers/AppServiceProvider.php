<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            'eloquent.creating: *',
            'eloquent.updating: *',
            'eloquent.deleting: *',
            'eloquent.restoring: *',
        ] as $event) {
            Event::listen(
                $event,
                function (string $eventName, array $data): void {
                    $model = $data[0] ?? null;

                    if (! $model instanceof Model) {
                        return;
                    }

                    $this->authorizeOrganizationWrite($model);
                },
            );
        }

        foreach ([
            'eloquent.creating: *',
            'eloquent.updating: *',
        ] as $event) {
            Event::listen(
                $event,
                function (string $eventName, array $data): void {
                    $model = $data[0] ?? null;

                    if (! $model instanceof Model) {
                        return;
                    }

                    $this->validateAssignee($model);
                },
            );
        }
    }

    private function authorizeOrganizationWrite(Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            // Console jobs, migrations and integrations without an authenticated
            // Central user keep their existing behaviour.
            return;
        }

        if (! array_key_exists('organization_id', $model->getAttributes())) {
            return;
        }

        $organizationIds = [];

        $currentOrganizationId = $model->getAttribute('organization_id');

        if ($currentOrganizationId !== null) {
            $organizationIds[] = (int) $currentOrganizationId;
        }

        if ($model->exists) {
            $originalOrganizationId = $model->getOriginal('organization_id');

            if ($originalOrganizationId !== null) {
                $organizationIds[] = (int) $originalOrganizationId;
            }
        }

        foreach (array_unique($organizationIds) as $organizationId) {
            if ($user->canWriteToOrganization($organizationId)) {
                continue;
            }

            throw new AuthorizationException(
                'Tu acceso a esta empresa es de solo lectura.',
            );
        }
    }

    private function validateAssignee(Model $model): void
    {
        $attributes = $model->getAttributes();

        if (
            ! array_key_exists('organization_id', $attributes)
            || ! array_key_exists('assigned_to', $attributes)
        ) {
            return;
        }

        $organizationId = $model->getAttribute('organization_id');
        $assigneeId = $model->getAttribute('assigned_to');

        if ($organizationId === null || $assigneeId === null) {
            return;
        }

        $assignable = User::query()
            ->whereKey($assigneeId)
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn ($query) => $query
                    ->where('organizations.id', $organizationId)
                    ->where('organizations.is_active', true)
                    ->where('organization_user.is_active', true)
                    ->whereIn(
                        'organization_user.role',
                        ['owner', 'admin', 'member'],
                    ),
            )
            ->exists();

        if ($assignable) {
            return;
        }

        throw ValidationException::withMessages([
            'assigned_to' =>
                'El responsable debe ser un usuario activo con permiso de trabajo en la empresa seleccionada.',
        ]);
    }
}
