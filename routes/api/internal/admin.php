<?php

use App\Http\Controllers\Api\Internal\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\Internal\V1\Admin\Referrals\AdminReferralCodeController;
use App\Http\Controllers\Api\Internal\V1\Admin\Referrals\AdminReferralController;
use App\Http\Controllers\Api\Internal\V1\Admin\Referrals\AdminReferralRewardController;
use App\Http\Controllers\Api\Internal\V1\Admin\Referrals\ReferralBlockedDomainController;
use App\Http\Controllers\Api\Internal\V1\Admin\Referrals\ReferralProgramController;
use App\Http\Controllers\Api\Internal\V1\Admin\Referrals\ReferralRewardRuleController;
use App\Http\Controllers\Api\Internal\V1\Admin\Referrals\ReferralSimulationController;
use App\Http\Controllers\Api\Internal\V1\Admin\Referrals\ReferralStatsController;
use Illuminate\Support\Facades\Route;

/*
| Platform-admin API. `platform.admin` requires `users.is_platform_admin`
| (set only by `php artisan admin:grant`) and confirmed two-factor auth.
*/
Route::middleware(['auth:api', 'platform.admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('audit-log', [AdminAuditLogController::class, 'index'])->name('audit-log.index');

        Route::prefix('referrals')->as('referrals.')->group(function () {
            Route::get('stats', [ReferralStatsController::class, 'show'])->name('stats');

            Route::get('programs', [ReferralProgramController::class, 'index'])->name('programs.index');
            Route::post('programs', [ReferralProgramController::class, 'store'])->name('programs.store');
            Route::get('programs/{program}', [ReferralProgramController::class, 'show'])->name('programs.show');
            Route::patch('programs/{program}', [ReferralProgramController::class, 'update'])->name('programs.update');
            Route::delete('programs/{program}', [ReferralProgramController::class, 'destroy'])->name('programs.destroy');
            Route::post('programs/{program}/make-default', [ReferralProgramController::class, 'makeDefault'])->name('programs.make-default');
            Route::post('programs/{program}/duplicate', [ReferralProgramController::class, 'duplicate'])->name('programs.duplicate');
            Route::post('programs/{program}/simulate', [ReferralSimulationController::class, 'store'])->name('programs.simulate');

            Route::get('programs/{program}/rules', [ReferralRewardRuleController::class, 'index'])->name('rules.index');
            Route::post('programs/{program}/rules', [ReferralRewardRuleController::class, 'store'])->name('rules.store');
            Route::patch('programs/{program}/rules/reorder', [ReferralRewardRuleController::class, 'reorder'])->name('rules.reorder');
            Route::patch('rules/{rule}', [ReferralRewardRuleController::class, 'update'])->name('rules.update');
            Route::delete('rules/{rule}', [ReferralRewardRuleController::class, 'destroy'])->name('rules.destroy');
            Route::post('rules/{rule}/toggle', [ReferralRewardRuleController::class, 'toggle'])->name('rules.toggle');

            Route::get('codes', [AdminReferralCodeController::class, 'index'])->name('codes.index');
            Route::patch('codes/{code}', [AdminReferralCodeController::class, 'update'])->name('codes.update');

            Route::get('referrals', [AdminReferralController::class, 'index'])->name('referrals.index');
            Route::get('referrals/{referral}', [AdminReferralController::class, 'show'])->name('referrals.show');
            Route::post('referrals/{referral}/reject', [AdminReferralController::class, 'reject'])->name('referrals.reject');
            Route::post('referrals/{referral}/restore', [AdminReferralController::class, 'restore'])->name('referrals.restore');

            Route::get('rewards', [AdminReferralRewardController::class, 'index'])->name('rewards.index');
            Route::post('rewards/manual', [AdminReferralRewardController::class, 'store'])->name('rewards.store');
            Route::post('rewards/{reward}/approve', [AdminReferralRewardController::class, 'approve'])->name('rewards.approve');
            Route::post('rewards/{reward}/grant-now', [AdminReferralRewardController::class, 'grantNow'])->name('rewards.grant-now');
            Route::post('rewards/{reward}/revoke', [AdminReferralRewardController::class, 'revoke'])->name('rewards.revoke');

            Route::get('blocked-domains', [ReferralBlockedDomainController::class, 'index'])->name('blocked-domains.index');
            Route::post('blocked-domains', [ReferralBlockedDomainController::class, 'store'])->name('blocked-domains.store');
            Route::delete('blocked-domains/{domain}', [ReferralBlockedDomainController::class, 'destroy'])->name('blocked-domains.destroy');
        });
    });
