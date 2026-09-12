<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
}
