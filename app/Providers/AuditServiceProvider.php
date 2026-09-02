<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Observers\AuditObserver;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([
            Organization::class,
            Client::class,
            Project::class,
            Task::class,
            ServiceOrder::class,
            RecurringObligation::class,
            ObligationOccurrence::class,
            Incident::class,
        ] as $model) {
            $model::observe(
                AuditObserver::class,
            );
        }
    }
}
