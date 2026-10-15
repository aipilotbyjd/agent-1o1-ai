<?php

namespace App\Services\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Runs referral admin operations for the super admin panel.
 *
 * The operations themselves are `ReferralAdmin`'s — the same ones the
 * platform-admin API uses — so the referral rules, reward handlers and
 * clawbacks behave identically. `ReferralAdmin` records them in the
 * platform-admin log with no actor (it only knows `User` admins); this adds
 * the entry that names the super admin, in the same transaction.
 */
class SuperAdminReferrals
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @param  array<string, mixed>|null  $details
     * @return TResult
     */
    public function run(SuperAdmin $actor, string $action, ?Model $subject, Closure $operation, ?array $details = null): mixed
    {
        return DB::transaction(function () use ($actor, $action, $subject, $operation, $details): mixed {
            $result = $operation();

            $this->audit->record($actor, $action, $subject ?? ($result instanceof Model ? $result : null), null, $details);

            return $result;
        });
    }
}
