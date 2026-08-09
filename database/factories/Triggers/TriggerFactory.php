<?php

namespace Database\Factories\Triggers;

use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trigger>
 */
class TriggerFactory extends Factory
{
    protected $model = Trigger::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'target_type' => TriggerTargetType::Workflow->value,
            'target_id' => fake()->numberBetween(1, 1000),
            'type' => TriggerType::Manual,
            'config' => [],
            'is_active' => true,
            'consecutive_failure_count' => 0,
        ];
    }

    public function webhook(): static
    {
        return $this->state(fn (): array => [
            'type' => TriggerType::Webhook,
            'token' => Trigger::generateToken(),
        ]);
    }

    public function schedule(string $cron = '0 9 * * *'): static
    {
        return $this->state(fn (): array => [
            'type' => TriggerType::Schedule,
            'config' => ['cron' => $cron],
        ]);
    }

    public function polling(int $intervalMinutes = 15): static
    {
        return $this->state(fn (): array => [
            'type' => TriggerType::Polling,
            'config' => ['poll_interval_minutes' => $intervalMinutes],
        ]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
