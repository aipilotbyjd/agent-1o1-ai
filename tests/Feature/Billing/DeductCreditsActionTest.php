<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

it('records a transaction and increments the current usage period', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $transaction = app(DeductCreditsAction::class)->execute(
        $workspace,
        CreditTransactionType::NodeRun,
        123,
        5,
        'Test charge',
    );

    expect($transaction->credits)->toBe(5);
    expect($transaction->source_type)->toBe(CreditTransactionType::NodeRun);
    expect($transaction->source_id)->toBe(123);

    $period = $workspace->currentUsagePeriod();
    expect($period->credits_used)->toBe(5);
});

it('accumulates across multiple charges in the same period', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 3);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::AgentStep, 2, 4);

    expect($workspace->currentUsagePeriod()->credits_used)->toBe(7);
    expect($workspace->creditTransactions()->count())->toBe(2);
});

it('throws once a charge would exceed the usage period credits_limit', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->update(['credits_limit' => 10]);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 8);

    expect(fn () => app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 5))
        ->toThrow(InsufficientCreditsException::class);

    // The over-limit attempt never wrote a transaction or incremented usage.
    expect($workspace->currentUsagePeriod()->credits_used)->toBe(8);
    expect($workspace->creditTransactions()->count())->toBe(1);
});
