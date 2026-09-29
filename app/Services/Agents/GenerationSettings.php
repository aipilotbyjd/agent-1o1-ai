<?php

namespace App\Services\Agents;

use App\Models\Agents\Agent;

/**
 * The per-agent sampling options handed to the provider — `temperature`
 * from its own column, the rest from the free-form `settings` array.
 * Unknown `settings` keys are ignored; a missing one leaves the SDK's
 * default in place.
 */
final readonly class GenerationSettings
{
    public function __construct(
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?int $maxSteps = null,
        public ?float $topP = null,
    ) {}

    public static function fromAgent(Agent $agent): self
    {
        $settings = $agent->settings ?? [];

        return new self(
            temperature: $agent->temperature !== null ? (float) $agent->temperature : null,
            maxTokens: isset($settings['max_tokens']) ? (int) $settings['max_tokens'] : null,
            maxSteps: isset($settings['max_steps']) ? (int) $settings['max_steps'] : null,
            topP: isset($settings['top_p']) ? (float) $settings['top_p'] : null,
        );
    }
}
