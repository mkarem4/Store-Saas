<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;

class ReleaseWalletFundsCommand extends Command
{
    protected $signature = 'wallet:release';

    protected $description = 'Move store earnings whose hold period ended from pending to available';

    public function handle(WalletService $wallet): int
    {
        $moved = $wallet->releaseMatured();

        $this->info("Released {$moved} wallet transaction(s).");

        return self::SUCCESS;
    }
}
