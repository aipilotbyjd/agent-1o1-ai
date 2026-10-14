<?php

namespace App\Console\Commands\Admin;

use App\Models\Admin\Admin;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Console\Command;

class EnableAdminCommand extends Command
{
    protected $signature = 'admin:enable {email : The admin account\'s email address}';

    protected $description = 'Re-enables a disabled admin panel account.';

    public function handle(AdminAuditLogger $audit): int
    {
        $admin = Admin::query()->where('email', $this->argument('email'))->first();

        if ($admin === null) {
            $this->error('No admin has that email address.');

            return self::FAILURE;
        }

        if (! $admin->isDisabled()) {
            $this->info("{$admin->email} is already enabled.");

            return self::SUCCESS;
        }

        $admin->forceFill(['disabled_at' => null])->save();

        $audit->record(null, 'admin.enabled', $admin);

        $this->info("{$admin->email} is enabled.");

        return self::SUCCESS;
    }
}
