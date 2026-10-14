<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use Illuminate\Console\Command;

/**
 * Blocks the super admin panel account without deleting it, so its audit history
 * keeps naming who did what. Open sessions end on their next request.
 */
class DisableSuperAdminCommand extends Command
{
    protected $signature = 'super-admin:disable {email : The admin account\'s email address}';

    protected $description = 'Disables the super admin panel account and ends its sessions.';

    public function handle(SuperAdminAuditLogger $audit): int
    {
        $superAdmin = SuperAdmin::query()->where('email', $this->argument('email'))->first();

        if ($superAdmin === null) {
            $this->error('No super admin has that email address.');

            return self::FAILURE;
        }

        if ($superAdmin->isDisabled()) {
            $this->info("{$superAdmin->email} is already disabled.");

            return self::SUCCESS;
        }

        $superAdmin->forceFill(['disabled_at' => now()])->save();

        $audit->record(null, 'super_admin.disabled', $superAdmin);

        $this->info("{$superAdmin->email} is disabled.");

        return self::SUCCESS;
    }
}
