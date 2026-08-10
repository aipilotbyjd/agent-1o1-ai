<?php

namespace Database\Factories\Billing;

use App\Models\Billing\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_monthly' => 2900,
            'price_yearly' => 29000,
            'credits_monthly' => 5000,
            'limits' => [],
            'features' => [],
            'stripe_product_id' => null,
            'stripe_price_id_monthly' => 'price_'.fake()->uuid(),
            'stripe_price_id_yearly' => 'price_'.fake()->uuid(),
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
