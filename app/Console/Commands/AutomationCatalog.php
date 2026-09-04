<?php

namespace App\Console\Commands;

use App\Support\AutomationRuleCatalog;
use Illuminate\Console\Command;

class AutomationCatalog extends Command
{
    protected $signature = 'central:automation-catalog';
    protected $description = 'Show the safe automation rule contract';

    public function handle(AutomationRuleCatalog $catalog): int
    {
        $this->line(
            json_encode(
                $catalog->contract(),
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
        );

        return self::SUCCESS;
    }
}
