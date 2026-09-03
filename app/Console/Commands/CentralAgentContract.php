<?php

namespace App\Console\Commands;

use App\Support\CentralAgentGateway;
use Illuminate\Console\Command;

class CentralAgentContract extends Command
{
    protected $signature = 'central:agent-contract';

    protected $description =
        'Show the internal read/preview-only Central agent contract';

    public function handle(
        CentralAgentGateway $gateway,
    ): int {
        $this->line(
            json_encode(
                $gateway->contract(),
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
        );

        return self::SUCCESS;
    }
}
