<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * super admin panel accounts have no "forgot password" page; this is the reset.
 * Every open session for the account ends on its next request.
 */
class ResetSuperAdminPasswordCommand extends Command
{
    protected $signature = 'super-admin:reset-password {email : The admin account\'s email address}';

    protected $description = 'Sets a new password for the super admin panel account and signs out its sessions.';

    public function handle(SuperAdminAuditLogger $audit): int
    {
        $superAdmin = SuperAdmin::query()->where('email', $this->argument('email'))->first();

        if ($superAdmin === null) {
            $this->error('No super admin has that email address.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');
        $confirmation = (string) $this->secret('Confirm new password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', SuperAdmin::passwordRule()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $superAdmin->forceFill(['password' => $password, 'password_changed_at' => now()])->save();

        $audit->record(null, 'super_admin.password.reset', $superAdmin);

        $this->info("Password reset for {$superAdmin->email}. Their open sessions have been ended.");

        return self::SUCCESS;
    }
}
