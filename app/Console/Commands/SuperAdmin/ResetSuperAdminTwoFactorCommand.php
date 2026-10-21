<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use App\Services\SuperAdmin\SuperAdminTwoFactor;
use Illuminate\Console\Command;

/**
 * For a super admin who lost their authenticator and their recovery codes. Clears
 * two-factor so it is enrolled again at the next sign-in; open sessions end
 * on their next request.
 */
class ResetSuperAdminTwoFactorCommand extends Command
{
    protected $signature = 'super-admin:reset-two-factor {email : The admin account\'s email address}';

    protected $description = 'Clears the super admin panel account\'s two-factor setup so it is enrolled again at next sign-in.';

    public function handle(SuperAdminTwoFactor $twoFactor, SuperAdminAuditLogger $audit): int
    {
        $superAdmin = SuperAdmin::query()->where('email', $this->argument('email'))->first();

        if ($superAdmin === null) {
            $this->error('No super admin has that email address.');

            return self::FAILURE;
        }

        if (! $this->confirm("Reset two-factor authentication for {$superAdmin->email}?")) {
            return self::FAILURE;
        }

        $twoFactor->reset($superAdmin);

        $audit->record(null, 'super_admin.two_factor.reset', $superAdmin);

        $this->info("Two-factor reset for {$superAdmin->email}. They will set it up again at next sign-in.");

        return self::SUCCESS;
    }
}
