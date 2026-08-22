<?php

namespace Database\Factories\Nodes;

use App\Models\Nodes\CustomNode;
use App\Models\Nodes\NodeCategory;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomNode>
 */
class CustomNodeFactory extends Factory
{
    protected $model = CustomNode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'category_id' => fn () => NodeCategory::factory()->create()->id,
            'type' => 'custom_'.Str::slug($name, '_').'_'.fake()->unique()->numberBetween(1, 999999),
            'name' => $name,
            'description' => fake()->sentence(),
            'icon' => 'box',
            'color' => '#6366f1',
            'config_schema' => ['type' => 'object', 'properties' => []],
        ];
    }

    /**
     * A node with a runnable HTTP implementation. The default factory state
     * deliberately leaves `implementation` null — that's the definition-only
     * row the table shipped with, and it must keep behaving as unrunnable.
     *
     * @param  array<string, mixed>  $implementation
     */
    public function executable(array $implementation = []): static
    {
        return $this->state(fn (): array => [
            'implementation' => [
                'kind' => 'http',
                'method' => 'POST',
                'url' => 'https://api.example.test/things',
                ...$implementation,
            ],
        ]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function inCategory(NodeCategory $category): static
    {
        return $this->state(fn (): array => ['category_id' => $category->id]);
    }
}
