<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\SuperAdminAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * The only way to create the super admin panel account. There is no sign-up page:
 * anyone who can run this already controls the server. The password is
 * prompted for, never passed as an argument, so it stays out of shell
 * history. Two-factor enrollment happens at the first sign-in.
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'super-admin:create {email : The email address the super admin signs in with} {--name= : Display name}';

    protected $description = 'Creates the super admin panel account (separate from customer users).';

    public function handle(SuperAdminAuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) $this->secret('Password (min 12 chars, upper & lower case, a number and a symbol)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'email' => ['required', 'email', 'max:255', 'unique:super_admins,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', SuperAdmin::passwordRule()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $superAdmin = SuperAdmin::query()->create(['email' => $email, 'name' => $name, 'password' => $password]);
        $superAdmin->forceFill(['password_changed_at' => now()])->save();

        $audit->record(null, 'super_admin.created', $superAdmin, null, ['email' => $email]);

        $this->info("Super admin {$email} created. Two-factor setup is required at first sign-in — sign in now to complete it.");

        return self::SUCCESS;
    }
}
