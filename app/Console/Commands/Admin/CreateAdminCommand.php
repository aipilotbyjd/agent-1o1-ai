<?php

namespace App\Console\Commands\Admin;

use App\Models\Admin\Admin;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * The only way to create an admin panel account. There is no sign-up page:
 * anyone who can run this already controls the server. The password is
 * prompted for, never passed as an argument, so it stays out of shell
 * history. Two-factor enrollment happens at the first sign-in.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email : The email address the admin signs in with} {--name= : Display name}';

    protected $description = 'Creates an admin panel account (separate from customer users).';

    public function handle(AdminAuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) $this->secret('Password (min 12 chars, upper & lower case, a number and a symbol)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Admin::passwordRule()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::query()->create(['email' => $email, 'name' => $name, 'password' => $password]);
        $admin->forceFill(['password_changed_at' => now()])->save();

        $audit->record(null, 'admin.created', $admin, null, ['email' => $email]);

        $this->info("Admin {$email} created. Two-factor setup is required at first sign-in — sign in now to complete it.");

        return self::SUCCESS;
    }
}
