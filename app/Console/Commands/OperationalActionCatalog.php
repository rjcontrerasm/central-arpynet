<?php

namespace App\Console\Commands;

use App\Support\OperationalTaskActionService;
use Illuminate\Console\Command;

class OperationalActionCatalog
    extends Command
{
    protected $signature =
        'central:action-catalog';

    protected $description =
        'Show the safe internal operational action catalog';

    public function handle(
        OperationalTaskActionService $actions,
    ): int {
        $this->line(
            json_encode(
                [
                    'status' => 'ok',
                    'scope' => 'task',
                    'public_api' => false,
                    'network_calls' => false,
                    'bulk_actions' => false,
                    'arbitrary_writes' => false,
                    'actions' =>
                        $actions->catalog(),
                ],
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
        );

        return self::SUCCESS;
    }
}
