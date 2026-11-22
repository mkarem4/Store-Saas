<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\BusinessProvisioner;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Create any new permissions and grant them to every store owner role';

    public function handle(BusinessProvisioner $provisioner): int
    {
        $count = 0;

        Business::query()->withoutGlobalScopes()->each(function (Business $business) use ($provisioner, &$count): void {
            $provisioner->syncRoles($business);
            $count++;
        });

        $this->info("Permissions synced for {$count} stores.");

        return self::SUCCESS;
    }
}
