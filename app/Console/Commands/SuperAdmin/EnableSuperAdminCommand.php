<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use Illuminate\Console\Command;

class EnableSuperAdminCommand extends Command
{
    protected $signature = 'super-admin:enable {email : The admin account\'s email address}';

    protected $description = 'Re-enables a disabled super admin panel account.';

    public function handle(SuperAdminAuditLogger $audit): int
    {
        $superAdmin = SuperAdmin::query()->where('email', $this->argument('email'))->first();

        if ($superAdmin === null) {
            $this->error('No super admin has that email address.');

            return self::FAILURE;
        }

        if (! $superAdmin->isDisabled()) {
            $this->info("{$superAdmin->email} is already enabled.");

            return self::SUCCESS;
        }

        $superAdmin->forceFill(['disabled_at' => null])->save();

        $audit->record(null, 'super_admin.enabled', $superAdmin);

        $this->info("{$superAdmin->email} is enabled.");

        return self::SUCCESS;
    }
}
