<?php

use App\Support\ObligationOccurrenceGenerator;

use App\Models\RecurringObligation;

use App\Models\Task;
use App\Support\TaskPriorityCalculator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'tasks:recalculate-priority',
    function (): void {
        $calculator = app(TaskPriorityCalculator::class);
        $updated = 0;

        Task::query()
            ->open()
            ->chunkById(
                200,
                function ($tasks) use (
                    $calculator,
                    &$updated,
                ): void {
                    foreach ($tasks as $task) {
                        $result = $calculator->calculate($task);

                        if (
                            $task->priority_score === $result['score']
                            && $task->priority_band === $result['band']
                        ) {
                            continue;
                        }

                        $task->forceFill([
                            'priority_score' => $result['score'],
                            'priority_band' => $result['band'],
                        ])->saveQuietly();

                        $updated++;
                    }
                },
            );

        $this->info(
            "Prioridades actualizadas: {$updated}",
        );
    },
)->purpose('Recalculate task priorities');

Schedule::command('tasks:recalculate-priority')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Artisan::command(
    'obligations:generate-occurrences',
    function (): void {
        $generator = app(
            ObligationOccurrenceGenerator::class,
        );

        $created = 0;

        RecurringObligation::query()
            ->where('is_active', true)
            ->chunkById(
                100,
                function ($obligations) use (
                    $generator,
                    &$created,
                ): void {
                    foreach ($obligations as $obligation) {
                        $created += $generator->generateFor(
                            $obligation,
                            now()->startOfDay(),
                            now()->addDays(120)->endOfDay(),
                        );
                    }
                },
            );

        $this->info(
            "Vencimientos creados: {$created}",
        );
    },
)->purpose('Generate recurring obligation occurrences');

Schedule::command('obligations:generate-occurrences')
    ->dailyAt('00:10')
    ->withoutOverlapping();

