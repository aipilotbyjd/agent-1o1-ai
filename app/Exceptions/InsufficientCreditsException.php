<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by `Actions\Billing\DeductCreditsAction` when a charge would push
 * the workspace's current `UsagePeriod` past its `credits_limit` — mapped to
 * 402 in `bootstrap/app.php`. Never fires today: `credits_limit` stays null
 * (unlimited) until Stripe plan tiers are wired (docs/PLAN.md Phase 7), but
 * the enforcement path is real and tested.
 */
class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int $creditsNeeded,
        public readonly int $creditsAvailable,
    ) {
        parent::__construct("Insufficient credits: this action needs {$creditsNeeded}, {$creditsAvailable} remaining.");
    }
}
