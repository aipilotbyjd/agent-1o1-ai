<?php

use App\Http\Controllers\SuperAdmin\Referrals\BlockedDomainController;
use App\Http\Controllers\SuperAdmin\Referrals\CodeController;
use App\Http\Controllers\SuperAdmin\Referrals\OverviewController;
use App\Http\Controllers\SuperAdmin\Referrals\ProgramController;
use App\Http\Controllers\SuperAdmin\Referrals\ReferralController;
use App\Http\Controllers\SuperAdmin\Referrals\RewardController;
use App\Http\Controllers\SuperAdmin\Referrals\RuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('referrals')->as('referrals.')->group(function () {
    Route::get('/', OverviewController::class)->name('overview');

    Route::get('programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::get('programs/create', [ProgramController::class, 'create'])->name('programs.create');
    Route::post('programs', [ProgramController::class, 'store'])->name('programs.store');

    Route::prefix('programs/{program}')->whereUuid('program')->as('programs.')->group(function () {
        Route::get('/', [ProgramController::class, 'show'])->name('show');
        Route::get('edit', [ProgramController::class, 'edit'])->name('edit');
        Route::put('/', [ProgramController::class, 'update'])->name('update');
        Route::delete('/', [ProgramController::class, 'destroy'])->name('destroy');
        Route::post('make-default', [ProgramController::class, 'makeDefault'])->name('make-default');
        Route::post('duplicate', [ProgramController::class, 'duplicate'])->name('duplicate');

        Route::get('rules/create', [RuleController::class, 'create'])->name('rules.create');
        Route::post('rules', [RuleController::class, 'store'])->name('rules.store');
    });

    Route::prefix('rules/{rule}')->whereUuid('rule')->as('rules.')->group(function () {
        Route::get('edit', [RuleController::class, 'edit'])->name('edit');
        Route::put('/', [RuleController::class, 'update'])->name('update');
        Route::delete('/', [RuleController::class, 'destroy'])->name('destroy');
        Route::post('toggle', [RuleController::class, 'toggle'])->name('toggle');
        Route::post('move', [RuleController::class, 'move'])->name('move');
    });

    Route::get('referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::get('referrals/{referral}', [ReferralController::class, 'show'])->whereUuid('referral')->name('referrals.show');
    Route::post('referrals/{referral}/reject', [ReferralController::class, 'reject'])->whereUuid('referral')->name('referrals.reject');
    Route::post('referrals/{referral}/restore', [ReferralController::class, 'restore'])->whereUuid('referral')->name('referrals.restore');

    Route::get('rewards', [RewardController::class, 'index'])->name('rewards.index');
    Route::get('rewards/create', [RewardController::class, 'create'])->name('rewards.create');
    Route::post('rewards', [RewardController::class, 'store'])->name('rewards.store');
    Route::post('rewards/{reward}/approve', [RewardController::class, 'approve'])->whereUuid('reward')->name('rewards.approve');
    Route::post('rewards/{reward}/grant-now', [RewardController::class, 'grantNow'])->whereUuid('reward')->name('rewards.grant-now');
    Route::post('rewards/{reward}/revoke', [RewardController::class, 'revoke'])->whereUuid('reward')->name('rewards.revoke');

    Route::get('codes', [CodeController::class, 'index'])->name('codes.index');
    Route::get('codes/{code}/edit', [CodeController::class, 'edit'])->whereUuid('code')->name('codes.edit');
    Route::put('codes/{code}', [CodeController::class, 'update'])->whereUuid('code')->name('codes.update');

    Route::get('blocked-domains', [BlockedDomainController::class, 'index'])->name('blocked-domains.index');
    Route::post('blocked-domains', [BlockedDomainController::class, 'store'])->name('blocked-domains.store');
    Route::delete('blocked-domains/{domain}', [BlockedDomainController::class, 'destroy'])->whereUuid('domain')->name('blocked-domains.destroy');
});
