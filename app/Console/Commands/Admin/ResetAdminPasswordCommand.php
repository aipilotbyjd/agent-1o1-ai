<?php

namespace App\Console\Commands\Admin;

use App\Models\Admin\Admin;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Admin panel accounts have no "forgot password" page; this is the reset.
 * Every open session for the account ends on its next request.
 */
class ResetAdminPasswordCommand extends Command
{
    protected $signature = 'admin:reset-password {email : The admin account\'s email address}';

    protected $description = 'Sets a new password for an admin panel account and signs out its sessions.';

    public function handle(AdminAuditLogger $audit): int
    {
        $admin = Admin::query()->where('email', $this->argument('email'))->first();

        if ($admin === null) {
            $this->error('No admin has that email address.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');
        $confirmation = (string) $this->secret('Confirm new password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Admin::passwordRule()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin->forceFill(['password' => $password, 'password_changed_at' => now()])->save();

        $audit->record(null, 'admin.password.reset', $admin);

        $this->info("Password reset for {$admin->email}. Their open sessions have been ended.");

        return self::SUCCESS;
    }
}
