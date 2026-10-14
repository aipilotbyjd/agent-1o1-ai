<?php

namespace App\Console\Commands\Admin;

use App\Models\Admin\Admin;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Console\Command;

/**
 * Blocks an admin panel account without deleting it, so its audit history
 * keeps naming who did what. Open sessions end on their next request.
 */
class DisableAdminCommand extends Command
{
    protected $signature = 'admin:disable {email : The admin account\'s email address}';

    protected $description = 'Disables an admin panel account and ends its sessions.';

    public function handle(AdminAuditLogger $audit): int
    {
        $admin = Admin::query()->where('email', $this->argument('email'))->first();

        if ($admin === null) {
            $this->error('No admin has that email address.');

            return self::FAILURE;
        }

        if ($admin->isDisabled()) {
            $this->info("{$admin->email} is already disabled.");

            return self::SUCCESS;
        }

        $admin->forceFill(['disabled_at' => now()])->save();

        $audit->record(null, 'admin.disabled', $admin);

        $this->info("{$admin->email} is disabled.");

        return self::SUCCESS;
    }
}
