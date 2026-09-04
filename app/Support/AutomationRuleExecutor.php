<?php

namespace App\Support;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\User;
use App\Notifications\AutomationInternalNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AutomationRuleExecutor
{
    public function __construct(
        private readonly AutomationRuleCatalog $catalog,
        private readonly AutomationRuleEngine $engine,
    ) {
    }

    public function runRule(
        AutomationRule $rule,
        int $limit = 100,
        ?CarbonImmutable $now = null,
    ): array {
        $this->catalog->validate(
            $rule->trigger_key,
            $rule->action_key,
            $rule->mode,
        );

        $now ??= CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $limit = max(
            1,
            min(100, $limit),
        );

        $candidates = $this->engine
            ->preview(
                $rule,
                $now,
            )
            ->take($limit)
            ->values();

        $summary = [
            'rule_id' => $rule->id,
            'name' => $rule->name,
            'mode' => $rule->mode,
            'matches' =>
                $candidates->count(),
            'executed' => 0,
            'pending_confirmation' => 0,
            'previewed' => 0,
            'blocked' => 0,
            'failed' => 0,
            'duplicates' => 0,
        ];

        foreach (
            $candidates
            as $candidate
        ) {
            $existing =
                AutomationRuleRun::query()
                    ->where(
                        'automation_rule_id',
                        $rule->id,
                    )
                    ->where(
                        'subject_type',
                        $candidate[
                            'subject_type'
                        ],
                    )
                    ->where(
                        'subject_id',
                        $candidate[
                            'subject_id'
                        ],
                    )
                    ->where(
                        'fingerprint',
                        $candidate[
                            'fingerprint'
                        ],
                    )
                    ->first();

            if (
                $existing
                && $existing->outcome
                    !== 'failed'
            ) {
                $summary['duplicates']++;

                continue;
            }

            $run = $existing
                ?: AutomationRuleRun::query()
                    ->create([
                        'automation_rule_id' =>
                            $rule->id,
                        'organization_id' =>
                            $rule
                                ->organization_id,
                        'subject_type' =>
                            $candidate[
                                'subject_type'
                            ],
                        'subject_id' =>
                            $candidate[
                                'subject_id'
                            ],
                        'fingerprint' =>
                            $candidate[
                                'fingerprint'
                            ],
                        'outcome' =>
                            'processing',
                        'payload' =>
                            $candidate,
                        'evaluated_at' =>
                            $now,
                    ]);

            if ($existing) {
                $run->forceFill([
                    'outcome' =>
                        'processing',
                    'payload' =>
                        $candidate,
                    'evaluated_at' =>
                        $now,
                    'executed_at' =>
                        null,
                    'error' => null,
                ])->save();
            }

            try {
                $outcome = match (
                    $rule->mode
                ) {
                    'preview' =>
                        'previewed',

                    'confirmation' =>
                        'pending_confirmation',

                    'automatic' =>
                        $this->executeAutomatic(
                            $rule,
                            $candidate,
                        ),

                    default => 'blocked',
                };

                $run->forceFill([
                    'outcome' => $outcome,
                    'executed_at' =>
                        $outcome === 'executed'
                            ? $now
                            : null,
                    'error' => null,
                ])->save();

                $summary[$outcome]++;
            } catch (Throwable $e) {
                report($e);

                $run->forceFill([
                    'outcome' => 'failed',
                    'error' => mb_substr(
                        $e->getMessage(),
                        0,
                        2000,
                    ),
                ])->save();

                $summary['failed']++;
            }
        }

        $rule->forceFill([
            'last_evaluated_at' =>
                $now,
        ])->saveQuietly();

        return $summary;
    }

    public function runActive(
        int $limit = 100,
        ?CarbonImmutable $now = null,
    ): Collection {
        return AutomationRule::query()
            ->where(
                'is_active',
                true,
            )
            ->orderBy('id')
            ->get()
            ->map(
                fn (
                    AutomationRule $rule,
                ): array =>
                    $this->runRule(
                        $rule,
                        $limit,
                        $now,
                    ),
            );
    }

    private function executeAutomatic(
        AutomationRule $rule,
        array $candidate,
    ): string {
        $definition =
            $this->catalog->definition(
                $rule->action_key,
            );

        if (
            ! $definition[
                'execution_supported'
            ]
            || ! $this->catalog
                ->isAutomaticInternal(
                    $rule->action_key,
                )
        ) {
            return 'blocked';
        }

        $recipient =
            $this->resolveRecipient(
                $rule,
            );

        if (! $recipient) {
            return 'blocked';
        }

        $recipient->notify(
            new AutomationInternalNotification(
                [
                    'title' =>
                        $definition['label'],
                    'message' =>
                        $candidate['title']
                        .' · '
                        .$candidate['reason'],
                    'rule_id' =>
                        $rule->id,
                    'action_key' =>
                        $rule->action_key,
                    'subject_type' =>
                        $candidate[
                            'subject_type'
                        ],
                    'subject_id' =>
                        $candidate[
                            'subject_id'
                        ],
                ],
            ),
        );

        return 'executed';
    }

    private function resolveRecipient(
        AutomationRule $rule,
    ): ?User {
        $preferredId = (int) (
            $rule->created_by
            ?? 0
        );

        if (
            $preferredId > 0
            && DB::table(
                'organization_user',
            )
                ->where(
                    'organization_id',
                    $rule
                        ->organization_id,
                )
                ->where(
                    'user_id',
                    $preferredId,
                )
                ->where(
                    'is_active',
                    true,
                )
                ->exists()
        ) {
            return User::query()->find(
                $preferredId,
            );
        }

        $fallbackId = DB::table(
            'organization_user',
        )
            ->where(
                'organization_id',
                $rule->organization_id,
            )
            ->where(
                'is_active',
                true,
            )
            ->orderByRaw(
                "CASE WHEN role = 'owner' THEN 0 ELSE 1 END",
            )
            ->orderBy('user_id')
            ->value('user_id');

        return $fallbackId
            ? User::query()->find(
                (int) $fallbackId,
            )
            : null;
    }
}
