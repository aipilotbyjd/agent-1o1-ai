<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Plan
    |--------------------------------------------------------------------------
    |
    | The plan a workspace falls back to when it has no *valid* subscription —
    | either it never subscribed, or its subscription lapsed (canceled, past
    | due, incomplete). Without this every unsubscribed workspace resolved to
    | a `null` plan, which `UsagePeriod::hasRemainingCredits()` treats as an
    | unlimited allowance. Resolved by `Plan::default()`; a missing/inactive
    | slug still yields `null` (unlimited) so a half-seeded install fails open
    | rather than locking every workspace out.
    |
    */

    'default_plan' => env('BILLING_DEFAULT_PLAN', 'free'),

    /*
    |--------------------------------------------------------------------------
    | Trial Ending Warning
    |--------------------------------------------------------------------------
    |
    | How many days before `trial_ends_at` `billing:notify-trial-ending` warns
    | a workspace that its trial is about to convert to a paid charge.
    |
    */

    'trial_ending_warning_days' => (int) env('BILLING_TRIAL_ENDING_WARNING_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Billing Intervals
    |--------------------------------------------------------------------------
    |
    | Estate-wide kill switch per billing window. Turning one off withdraws it
    | from sale across every plan at once — use it to pull `lifetime` from the
    | whole catalog without editing each plan's `enabled_intervals`.
    |
    | This only blocks *new* purchases. Workspaces already entitled by a
    | subscription or a plan grant on a disabled interval keep what they paid
    | for; see `Plan::isSoldOn()`.
    |
    */

    'intervals' => [
        'monthly' => (bool) env('BILLING_INTERVAL_MONTHLY_ENABLED', true),
        'quarterly' => (bool) env('BILLING_INTERVAL_QUARTERLY_ENABLED', true),
        'yearly' => (bool) env('BILLING_INTERVAL_YEARLY_ENABLED', true),
        'lifetime' => (bool) env('BILLING_INTERVAL_LIFETIME_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Packs
    |--------------------------------------------------------------------------
    |
    | One-time credit top-ups, purchasable via Stripe Checkout regardless of
    | Stripe price/product setup — `checkoutCharge()` builds the Checkout
    | Session from `price_cents` directly, so no Stripe Dashboard config is
    | needed to add or resize a pack. Purchased credits land in the
    | workspace's non-expiring `topup_credits` pool, not the current usage
    | period — see `ActivateCreditPackAction`.
    |
    */

    'packs' => [
        'small' => [
            'label' => '1,000 credits',
            'credits' => 1000,
            'price_cents' => 900,
        ],
        'medium' => [
            'label' => '5,000 credits',
            'credits' => 5000,
            'price_cents' => 3900,
        ],
        'large' => [
            'label' => '20,000 credits',
            'credits' => 20000,
            'price_cents' => 12900,
        ],
    ],

];
