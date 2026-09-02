<?php

use App\Services\GoogleCalendarSyncService;

use App\Models\GoogleCalendarConnection;

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

Artisan::command(
    'calendar:sync',
    function (): void {
        $service = app(GoogleCalendarSyncService::class);

        GoogleCalendarConnection::query()
            ->with('user')
            ->chunkById(
                50,
                function ($connections) use ($service): void {
                    foreach ($connections as $connection) {
                        if (! $connection->user) {
                            continue;
                        }

                        try {
                            $result = $service->syncUser(
                                $connection->user,
                            );

                            $this->info(
                                $connection->user->email
                                .' | creados: '.$result['created']
                                .' | actualizados: '.$result['updated']
                                .' | sin cambios: '.$result['unchanged']
                                .' | eliminados: '.$result['deleted']
                                .' | errores: '.$result['errors'],
                            );
                        } catch (Throwable $exception) {
                            report($exception);

                            $connection->forceFill([
                                'last_error_at' => now(),
                                'last_error' => $exception->getMessage(),
                            ])->save();

                            $this->error(
                                $connection->user->email
                                .' | '.$exception->getMessage(),
                            );
                        }
                    }
                },
            );
    },
)->purpose('Synchronize Central items with Google Calendar');

Schedule::command('calendar:sync')
    ->everyTenMinutes()
    ->withoutOverlapping();


Artisan::command(
    'monitor:casa-andina-sync',
    function (): int {
        $service = app(
            \App\Services\CasaAndinaMonitorSyncService::class,
        );

        if (! $service->enabled()) {
            $this->info(
                'Casa Andina Monitor: deshabilitado. '
                .'No se realizó ninguna consulta externa.',
            );

            return self::SUCCESS;
        }

        if (! $service->configured()) {
            $this->error(
                'Casa Andina Monitor: faltan URL o token.',
            );

            return self::FAILURE;
        }

        try {
            $result = $service->sync();
        } catch (\Throwable $exception) {
            report($exception);

            $this->error(
                'Casa Andina Monitor: '
                .$exception->getMessage(),
            );

            return self::FAILURE;
        }

        $this->info(
            'Casa Andina Monitor'
            .' | items: '.$result['items']
            .' | creados: '.$result['created']
            .' | actualizados: '.$result['updated']
            .' | resueltos: '.$result['resolved']
            .' | sin cambios: '.$result['unchanged'],
        );

        return self::SUCCESS;
    },
)->purpose(
    'Synchronize Casa Andina Monitor incidents with Central',
);

Schedule::command('monitor:casa-andina-sync')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('summary:deliver today')
    ->dailyAt(config('central.summary.daily_time', '07:30'))
    ->timezone(config('app.timezone', 'America/Lima'))
    ->withoutOverlapping();

Schedule::command('summary:deliver week')
    ->weeklyOn(
        config('central.summary.weekly_day', 1),
        config('central.summary.weekly_time', '07:35'),
    )
    ->timezone(config('app.timezone', 'America/Lima'))
    ->withoutOverlapping();


Schedule::command('summary:email today')
    ->dailyAt(config('central.summary.daily_time', '07:30'))
    ->timezone(config('app.timezone', 'America/Lima'))
    ->withoutOverlapping();

Schedule::command('summary:email week')
    ->weeklyOn(
        config('central.summary.weekly_day', 1),
        config('central.summary.weekly_time', '07:35'),
    )
    ->timezone(config('app.timezone', 'America/Lima'))
    ->withoutOverlapping();
