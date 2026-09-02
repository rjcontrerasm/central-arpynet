<?php

namespace App\Support;

use App\Models\RecurringTaskRule;
use App\Models\RecurringTaskRun;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecurringTaskGenerator
{
    public function generateFor(
        RecurringTaskRule $rule,
        CarbonInterface $now,
    ): int {
        if (
            ! $rule->is_active
            || ! $rule->anchor_date
        ) {
            return 0;
        }

        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $today = CarbonImmutable::parse(
            $now->toDateString(),
            $timezone,
        )->startOfDay();

        $horizon = $today->addDays(
            max(
                0,
                min(
                    90,
                    (int) $rule
                        ->create_days_before,
                ),
            ),
        );

        $anchor = CarbonImmutable::parse(
            $rule->anchor_date
                ->toDateString(),
            $timezone,
        )->startOfDay();

        $endDate = $rule->end_date
            ? CarbonImmutable::parse(
                $rule->end_date
                    ->toDateString(),
                $timezone,
            )->endOfDay()
            : null;

        if (
            $endDate
            && $endDate->lt($today)
        ) {
            return 0;
        }

        $cursor = $anchor;

        /*
         * We never backfill historical tasks.
         * A rule starts producing from today
         * forward, preventing old anchors from
         * flooding Central on first activation.
         */
        while ($cursor->lt($today)) {
            $cursor = $this->next(
                $cursor,
                $rule->frequency,
            );
        }

        $created = 0;

        while ($cursor->lte($horizon)) {
            if (
                $endDate
                && $cursor->gt($endDate)
            ) {
                break;
            }

            $created +=
                $this->generateOccurrence(
                    $rule,
                    $cursor,
                    $timezone,
                );

            $cursor = $this->next(
                $cursor,
                $rule->frequency,
            );
        }

        return $created;
    }

    private function generateOccurrence(
        RecurringTaskRule $rule,
        CarbonImmutable $scheduledFor,
        string $timezone,
    ): int {
        return DB::transaction(
            function () use (
                $rule,
                $scheduledFor,
                $timezone,
            ): int {
                $date =
                    $scheduledFor
                        ->toDateString();

                /*
                 * Normalizamos la clave exactamente al
                 * formato datetime que Eloquent usa en
                 * SQLite y que MySQL puede comparar con
                 * una columna DATE. Esto evita buscar
                 * "2026-09-02" y luego almacenar
                 * "2026-09-02 00:00:00".
                 *
                 * insertOrIgnore convierte el índice
                 * único en el guardián real de la
                 * idempotencia. lockForUpdate protege
                 * además la creación de la Task cuando
                 * dos ejecuciones coinciden.
                 */
                $scheduledKey =
                    CarbonImmutable::parse(
                        $date,
                        $timezone,
                    )->startOfDay();

                $timestamp = now();

                DB::table(
                    'recurring_task_runs',
                )->insertOrIgnore([
                    'recurring_task_rule_id' =>
                        $rule->id,
                    'organization_id' =>
                        $rule->organization_id,
                    'scheduled_for' =>
                        $scheduledKey,
                    'created_at' =>
                        $timestamp,
                    'updated_at' =>
                        $timestamp,
                ]);

                $run =
                    RecurringTaskRun::query()
                        ->where(
                            'recurring_task_rule_id',
                            $rule->id,
                        )
                        ->where(
                            'scheduled_for',
                            $scheduledKey,
                        )
                        ->lockForUpdate()
                        ->first();

                /*
                 * Fallback defensivo para motores que
                 * normalizan DATE eliminando la parte
                 * horaria al persistir.
                 */
                $run ??=
                    RecurringTaskRun::query()
                        ->where(
                            'recurring_task_rule_id',
                            $rule->id,
                        )
                        ->whereDate(
                            'scheduled_for',
                            $date,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if ($run->task_id) {
                    return 0;
                }

                $time = substr(
                    (string) (
                        $rule->due_time
                        ?: '17:00:00'
                    ),
                    0,
                    8,
                );

                if (
                    preg_match(
                        '/^\d{2}:\d{2}$/',
                        $time,
                    ) === 1
                ) {
                    $time .= ':00';
                }

                $dueAt =
                    CarbonImmutable::parse(
                        $date.' '.$time,
                        $timezone,
                    );

                $task = Task::query()
                    ->create([
                        'organization_id' =>
                            $rule
                                ->organization_id,
                        'project_id' =>
                            $rule->project_id,
                        'title' =>
                            $rule->title,
                        'description' =>
                            $rule->description,
                        'next_action' =>
                            $rule->next_action,
                        'status' => 'pending',
                        'urgency' =>
                            $rule->urgency,
                        'impact' =>
                            $rule->impact,
                        'due_at' =>
                            $dueAt,
                        'source' =>
                            'recurring',
                        'external_system' =>
                            'central_recurring_task',
                        'external_id' =>
                            'rule:'
                            .$rule->id
                            .':'
                            .$date,
                        'assigned_to' =>
                            $rule->assigned_to
                            ?? $rule->created_by,
                        'created_by' =>
                            $rule->created_by,
                        'is_private' =>
                            (bool) (
                                $rule->is_private
                                ?? false
                            ),
                    ]);

                $run->forceFill([
                    'task_id' =>
                        $task->id,
                    'generated_at' =>
                        now(),
                ])->save();

                return 1;
            },
        );
    }

    private function next(
        CarbonImmutable $date,
        string $frequency,
    ): CarbonImmutable {
        return match ($frequency) {
            'daily' =>
                $date->addDay(),
            'weekly' =>
                $date->addWeek(),
            'bimonthly' =>
                $date->addMonthsNoOverflow(
                    2,
                ),
            'quarterly' =>
                $date->addMonthsNoOverflow(
                    3,
                ),
            'semiannual' =>
                $date->addMonthsNoOverflow(
                    6,
                ),
            'annual' =>
                $date->addYearNoOverflow(),
            default =>
                $date->addMonthNoOverflow(),
        };
    }
}
