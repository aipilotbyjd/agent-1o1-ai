<?php

namespace Database\Factories\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TriggerEvent>
 */
class TriggerEventFactory extends Factory
{
    protected $model = TriggerEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trigger_id' => Trigger::factory(),
            'source' => TriggerType::Manual,
            'status' => TriggerEventStatus::Queued,
            'payload' => [],
            'delivery_id' => fake()->uuid(),
            'attempts' => 0,
            'duplicate_count' => 0,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => ['status' => TriggerEventStatus::Queued]);
    }

    public function running(): static
    {
        return $this->state(fn (): array => ['status' => TriggerEventStatus::Running]);
    }

    public function fired(): static
    {
        return $this->state(fn (): array => [
            'status' => TriggerEventStatus::Fired,
            'workflow_run_id' => fake()->numberBetween(1, 1000),
            'processed_at' => now(),
        ]);
    }
}
