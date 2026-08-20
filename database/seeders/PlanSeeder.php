<?php

namespace Database\Seeders;

use App\Models\Billing\Plan;
use Illuminate\Database\Seeder;

/**
 * The plan catalog. Paid plans read their Stripe price IDs from env — they
 * must be created ahead of time in the Stripe dashboard: three recurring
 * prices (monthly, every-3-months, yearly) plus a one-time price for the
 * lifetime purchase. A price ID left unset simply means the plan isn't sold
 * on that interval, which is how the Free plan is sold on none.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;

        foreach ($this->catalog() as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [...$plan, 'is_active' => true, 'sort_order' => $sort++],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Get started with a small monthly credit allowance.',
                'price_monthly' => 0,
                'price_quarterly' => 0,
                'price_yearly' => 0,
                'price_lifetime' => 0,
                'credits_monthly' => 100,
                'limits' => ['workflows' => 3, 'agents' => 1, 'members' => 2],
                'features' => [],
                'stripe_product_id' => null,
                'stripe_price_id_monthly' => null,
                'stripe_price_id_quarterly' => null,
                'stripe_price_id_yearly' => null,
                'stripe_price_id_lifetime' => null,
                'enabled_intervals' => [],
                'trial_days' => 0,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For small teams automating their first workflows.',
                'price_monthly' => 2900,
                'price_quarterly' => 7900,
                'price_yearly' => 29000,
                'price_lifetime' => 49000,
                'credits_monthly' => 5000,
                'limits' => ['workflows' => 25, 'agents' => 10, 'members' => 5],
                'features' => ['git_sync' => true, 'workflow_approvals' => true, 'credit_packs' => true],
                'stripe_product_id' => env('STRIPE_PLAN_STARTER_PRODUCT_ID'),
                'stripe_price_id_monthly' => env('STRIPE_PLAN_STARTER_MONTHLY_PRICE_ID'),
                'stripe_price_id_quarterly' => env('STRIPE_PLAN_STARTER_QUARTERLY_PRICE_ID'),
                'stripe_price_id_yearly' => env('STRIPE_PLAN_STARTER_YEARLY_PRICE_ID'),
                'stripe_price_id_lifetime' => env('STRIPE_PLAN_STARTER_LIFETIME_PRICE_ID'),
                'enabled_intervals' => ['monthly', 'quarterly', 'yearly', 'lifetime'],
                'trial_days' => 14,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Unlimited workflows and agents for scaling teams.',
                'price_monthly' => 9900,
                'price_quarterly' => 26900,
                'price_yearly' => 99000,
                'price_lifetime' => 149000,
                'credits_monthly' => 25000,
                'limits' => ['workflows' => -1, 'agents' => -1, 'members' => -1],
                'features' => ['git_sync' => true, 'workflow_approvals' => true, 'custom_nodes' => true, 'priority_support' => true, 'credit_packs' => true],
                'stripe_product_id' => env('STRIPE_PLAN_PRO_PRODUCT_ID'),
                'stripe_price_id_monthly' => env('STRIPE_PLAN_PRO_MONTHLY_PRICE_ID'),
                'stripe_price_id_quarterly' => env('STRIPE_PLAN_PRO_QUARTERLY_PRICE_ID'),
                'stripe_price_id_yearly' => env('STRIPE_PLAN_PRO_YEARLY_PRICE_ID'),
                'stripe_price_id_lifetime' => env('STRIPE_PLAN_PRO_LIFETIME_PRICE_ID'),
                'enabled_intervals' => ['monthly', 'quarterly', 'yearly', 'lifetime'],
                'trial_days' => 14,
            ],
        ];
    }
}
