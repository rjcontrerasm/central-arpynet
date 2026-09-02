<?php

namespace App\Console\Commands;

use App\Models\RecurringTaskRule;
use App\Support\RecurringTaskGenerator;
use Illuminate\Console\Command;

class GenerateRecurringTasks extends Command
{
    protected $signature =
        'tasks:generate-recurring';

    protected $description =
        'Generate idempotent tasks from active recurring rules';

    public function handle(
        RecurringTaskGenerator $generator,
    ): int {
        $created = 0;
        $rules = 0;

        RecurringTaskRule::query()
            ->where(
                'is_active',
                true,
            )
            ->chunkById(
                100,
                function ($items) use (
                    $generator,
                    &$created,
                    &$rules,
                ): void {
                    foreach ($items as $rule) {
                        $rules++;

                        $created +=
                            $generator
                                ->generateFor(
                                    $rule,
                                    now(),
                                );
                    }
                },
            );

        $this->info(
            'Reglas revisadas: '
            .$rules
            .' | tareas creadas: '
            .$created,
        );

        return self::SUCCESS;
    }
}
