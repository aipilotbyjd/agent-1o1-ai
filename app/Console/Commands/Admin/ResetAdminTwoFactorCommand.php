<?php

namespace App\Console\Commands\Admin;

use App\Models\Admin\Admin;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\AdminTwoFactor;
use Illuminate\Console\Command;

/**
 * For an admin who lost their authenticator and their recovery codes. Clears
 * two-factor so it is enrolled again at the next sign-in; open sessions end
 * on their next request.
 */
class ResetAdminTwoFactorCommand extends Command
{
    protected $signature = 'admin:reset-two-factor {email : The admin account\'s email address}';

    protected $description = 'Clears an admin panel account\'s two-factor setup so it is enrolled again at next sign-in.';

    public function handle(AdminTwoFactor $twoFactor, AdminAuditLogger $audit): int
    {
        $admin = Admin::query()->where('email', $this->argument('email'))->first();

        if ($admin === null) {
            $this->error('No admin has that email address.');

            return self::FAILURE;
        }

        if (! $this->confirm("Reset two-factor authentication for {$admin->email}?")) {
            return self::FAILURE;
        }

        $twoFactor->reset($admin);

        $audit->record(null, 'admin.two_factor.reset', $admin);

        $this->info("Two-factor reset for {$admin->email}. They will set it up again at next sign-in.");

        return self::SUCCESS;
    }
}
