<?php

namespace App\Console\Commands\Admin;

use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Console\Command;

class RevokePlatformAdminCommand extends Command
{
    protected $signature = 'admin:revoke {email : The email address of a platform admin}';

    protected $description = 'Removes platform admin access from a user.';

    public function handle(AdminAuditLogger $audit): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user has that email address.');

            return self::FAILURE;
        }

        if (! $user->isPlatformAdmin()) {
            $this->info("{$user->email} is not a platform admin.");

            return self::SUCCESS;
        }

        $user->forceFill(['is_platform_admin' => false])->save();

        $audit->record(null, 'platform_admin.revoked', $user, ['is_platform_admin' => true], ['is_platform_admin' => false]);

        $this->info("{$user->email} is no longer a platform admin.");

        return self::SUCCESS;
    }
}
