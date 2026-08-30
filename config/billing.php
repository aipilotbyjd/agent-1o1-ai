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
    | Gumloop's "Custom and MCP nodes: 3 credits each". `agent` matches
    | Gumloop's own stated Agent-node price: "a base cost of 3 credits per
    | run, charged on top of the actual credit cost of running the agent"
    | (docs/gumloop/output/raw/core-concepts/agent_node.md's "Credit Costs"
    | section) — `CreditMeter::costForNodeRun()` already adds this on top of
    | the node's own token cost. Add an entry here for any future node type
    | that deserves its own surcharge (a real custom/MCP node, image
    | generation, data enrichment, ...) instead of changing `CreditMeter`.
    |
    */

    'node_costs' => [
        'run_code' => (int) env('BILLING_NODE_COST_RUN_CODE', 3),
        'agent' => (int) env('BILLING_NODE_COST_AGENT', 3),
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
    | This app's `ModelCatalogSeeder` lists a mostly fictional, future-dated
    | model lineup (Claude Opus 5, GPT-5.6 *, Gemini 3.x, Grok 4.6, DeepSeek
    | V4, Kimi K3, GLM-5.2, MiniMax M3, Qwen3.6, Mistral Medium 3.5, ...) —
    | none of those exist yet, so there's no real price to put here, and this
    | table deliberately does not guess one. Every unpriced model (the
    | fictional ones, plus any real one not yet added below) falls back to
    | `CreditMeter`'s flat per-1,000-token ratio rather than being charged 0
    | credits.
    |
    | The rates below are best-effort, not independently verified this
    | session against each vendor's current pricing page — treat them as a
    | starting point and confirm against the vendor before relying on them
    | for real billing, the same way `ModelCatalogSeeder` flags its own
    | best-effort aggregator model ids. Add a row here once a model's price
    | is confirmed.
    |
    */

    'model_prices' => [
        // OpenAI — unverified, confirm against OpenAI's current pricing.
        'openai:gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'openai:gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],

        // Anthropic — unverified, confirm against Anthropic's current
        // pricing. Keyed to Claude Haiku 4.5's primary route
        // (`ModelCatalogSeeder`'s enabled anthropic route).
        'anthropic:claude-haiku-4-5-20251001' => ['input' => 1.00, 'output' => 5.00],

        // Fireworks — unverified, confirm against Fireworks' current
        // pricing for Meta's Llama 3.1 405B. Every route for this catalog
        // entry ships disabled by default (aggregator-only, no direct Meta
        // API); keyed to Fireworks' model id since that's the seeded
        // priority-0 route — re-key this if a different route (e.g.
        // Together) becomes primary instead.
        'fireworks:accounts/fireworks/models/llama-v3p1-405b-instruct' => ['input' => 3.00, 'output' => 3.00],
    ],

];
