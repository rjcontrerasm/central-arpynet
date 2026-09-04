<?php

namespace App\Console\Commands;

use App\Models\AutomationRule;
use App\Support\AutomationRuleEngine;
use Illuminate\Console\Command;

class AutomationPreview extends Command
{
    protected $signature = 'central:automation-preview {--rule=}';
    protected $description = 'Preview automation matches without executing writes';

    public function handle(AutomationRuleEngine $engine): int
    {
        $ruleId = $this->option('rule');

        if ($ruleId !== null) {
            $rule = AutomationRule::query()->find((int) $ruleId);

            if (! $rule) {
                $this->error('Automation rule not found.');
                return self::FAILURE;
            }

            $matches = $engine->preview($rule);

            $this->line(
                json_encode(
                    [
                        'status' => 'ok',
                        'preview_only' => true,
                        'writes' => 0,
                        'rule_id' => $rule->id,
                        'name' => $rule->name,
                        'mode' => $rule->mode,
                        'matches' => $matches->count(),
                    ],
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
                ),
            );

            return self::SUCCESS;
        }

        $rules = $engine->previewActive();

        $this->line(
            json_encode(
                [
                    'status' => 'ok',
                    'preview_only' => true,
                    'writes' => 0,
                    'active_rules' => $rules->count(),
                    'rules' => $rules,
                ],
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
        );

        return self::SUCCESS;
    }
}
