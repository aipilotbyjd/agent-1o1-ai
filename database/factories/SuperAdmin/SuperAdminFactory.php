<?php

namespace Database\Factories\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<SuperAdmin>
 */
class SuperAdminFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'password_changed_at' => now(),
        ];
    }

    /**
     * A super admin who has finished two-factor enrollment.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_recovery_codes' => [Hash::make('recovery-code-one'), Hash::make('recovery-code-two')],
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'disabled_at' => now(),
        ]);
    }
}
