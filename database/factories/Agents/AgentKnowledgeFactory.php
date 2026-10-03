<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentKnowledge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentKnowledge>
 */
class AgentKnowledgeFactory extends Factory
{
    protected $model = AgentKnowledge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = $this->faker->paragraph();

        return [
            'agent_id' => Agent::factory(),
            'title' => $this->faker->sentence(3),
            'content' => $content,
            'source_type' => 'text',
            'tokens' => AgentKnowledge::estimateTokens($content),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }
}
