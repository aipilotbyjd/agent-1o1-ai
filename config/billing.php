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

    /*
    |--------------------------------------------------------------------------
    | Node Credit Costs
    |--------------------------------------------------------------------------
    |
    | Flat credits a workflow node's type adds on top of `CreditMeter`'s base
    | 1 credit per run, keyed by the node's `type()` string (matches
    | `workflow_nodes.type` / `NodeRun.type`). A type left out of this table
    | costs nothing extra — most nodes (transform, filter, Slack, Gmail,
    | GitHub, Google Suite, ...) are free beyond the base credit, same as
    | Gumloop's node pricing.
    |
    | `run_code` is priced here because it executes in an isolated sandbox
    | for security, which carries a real infra cost — the same bracket as
    | Gumloop's "Custom and MCP nodes: 3 credits each". Add an entry here
    | for any future node type that deserves its own surcharge (a real
    | custom/MCP node, image generation, data enrichment, ...) instead of
    | changing `CreditMeter`.
    |
    */

    'node_costs' => [
        'run_code' => (int) env('BILLING_NODE_COST_RUN_CODE', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Value
    |--------------------------------------------------------------------------
    |
    | What one credit is worth in USD — mirrors Gumloop's "$1 buys 200
    | credits" ($0.005/credit). `CreditMeter` divides a model call's real
    | dollar cost (from `model_prices` below) by this to get its credit
    | price, rounded up.
    |
    */

    'credit_value_usd' => (float) env('BILLING_CREDIT_VALUE_USD', 0.005),

    /*
    |--------------------------------------------------------------------------
    | Orchestration Fee
    |--------------------------------------------------------------------------
    |
    | Gumloop's "running the agent loop itself" surcharge on agent chats —
    | applied by `CreditMeter` to the sum of an agent turn's reasoning, tool
    | call, and compute credits. Workflow runs never carry this fee; see
    | Gumloop's "Compute and the orchestration fee apply to agent chats
    | only."
    |
    */

    'orchestration_fee_rate' => (float) env('BILLING_ORCHESTRATION_FEE_RATE', 0.08),

    /*
    |--------------------------------------------------------------------------
    | Model Prices
    |--------------------------------------------------------------------------
    |
    | Real $ per 1M tokens, keyed by "{provider}:{model}" — the same
    | provider/model strings `laravel/ai` reports back on a response's
    | `Meta` (and that match `model_routes.execution_provider`/
    | `execution_model_id`). `CreditMeter` uses this to bill a model call at
    | its real cost (`credit_value_usd` above), the same way Gumloop's
    | "Model calls bill at cost" works.
    |
    | This app's `ModelCatalogSeeder` lists a fictional, future-dated model
    | lineup with no real published pricing — only `gpt-4o`/`gpt-4o-mini`
    | below are real, current public rates, kept as worked examples. Every
    | other model has no entry and falls back to `CreditMeter`'s flat
    | per-1,000-token ratio rather than being charged 0 credits. Add a row
    | here once a model's real vendor pricing is known.
    |
    */

    'model_prices' => [
        'openai:gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'openai:gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    ],

];
