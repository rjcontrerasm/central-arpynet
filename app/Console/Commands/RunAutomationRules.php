<?php

namespace App\Console\Commands;

use App\Models\AutomationRule;
use App\Support\AutomationRuleExecutor;
use Illuminate\Console\Command;

class RunAutomationRules extends Command
{
    protected $signature =
        'central:automation-run
        {--rule= : Execute one active rule}
        {--limit=100 : Maximum candidates per rule}';

    protected $description =
        'Run active safe automation rules manually';

    public function handle(
        AutomationRuleExecutor $executor,
    ): int {
        $limit = max(
            1,
            min(
                100,
                (int) $this->option(
                    'limit',
                ),
            ),
        );

        $ruleId = $this->option(
            'rule',
        );

        if ($ruleId !== null) {
            $rule =
                AutomationRule::query()
                    ->whereKey(
                        (int) $ruleId,
                    )
                    ->where(
                        'is_active',
                        true,
                    )
                    ->first();

            if (! $rule) {
                $this->error(
                    'Active automation rule not found.',
                );

                return self::FAILURE;
            }

            $result =
                $executor->runRule(
                    $rule,
                    $limit,
                );

            $this->line(
                json_encode(
                    [
                        'status' => 'ok',
                        'scheduler' => false,
                        'external_channels' =>
                            false,
                        'subject_mutations' =>
                            false,
                        ...$result,
                    ],
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
                ),
            );

            return self::SUCCESS;
        }

        $results =
            $executor->runActive(
                $limit,
            );

        $this->line(
            json_encode(
                [
                    'status' => 'ok',
                    'scheduler' => false,
                    'external_channels' =>
                        false,
                    'subject_mutations' =>
                        false,
                    'active_rules' =>
                        $results->count(),
                    'executed' =>
                        $results->sum(
                            'executed',
                        ),
                    'pending_confirmation' =>
                        $results->sum(
                            'pending_confirmation',
                        ),
                    'previewed' =>
                        $results->sum(
                            'previewed',
                        ),
                    'blocked' =>
                        $results->sum(
                            'blocked',
                        ),
                    'failed' =>
                        $results->sum(
                            'failed',
                        ),
                    'duplicates' =>
                        $results->sum(
                            'duplicates',
                        ),
                    'rules' => $results,
                ],
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
        );

        return self::SUCCESS;
    }
}
