<?php

namespace App\Console\Commands\Admin;

use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Console\Command;

/**
 * The only way to make someone a platform admin. There is deliberately no
 * API for it: anyone who can reach this command already controls the
 * server.
 */
class GrantPlatformAdminCommand extends Command
{
    protected $signature = 'admin:grant {email : The email address of an existing user}';

    protected $description = 'Makes an existing user a platform admin, allowing them into the /v1/admin API.';

    public function handle(AdminAuditLogger $audit): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user has that email address.');

            return self::FAILURE;
        }

        if ($user->isPlatformAdmin()) {
            $this->info("{$user->email} is already a platform admin.");

            return self::SUCCESS;
        }

        $user->forceFill(['is_platform_admin' => true])->save();

        $audit->record(null, 'platform_admin.granted', $user, ['is_platform_admin' => false], ['is_platform_admin' => true]);

        $this->info("{$user->email} is now a platform admin.");

        if (config('platform_admin.require_two_factor') && ! $user->hasTwoFactorEnabled()) {
            $this->warn('They must enable two-factor authentication before the admin API will accept them.');
        }

        return self::SUCCESS;
    }
}
