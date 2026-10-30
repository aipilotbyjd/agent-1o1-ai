<?php

namespace App\Nodes\Integrations\Tavily;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TavilyGetResearchNode extends AbstractTavilyNode
{
    public function type(): string
    {
        return 'tavily_get_research';
    }

    public function name(): string
    {
        return 'Tavily: Get Research';
    }

    public function description(): string
    {
        return 'Reads how a research run is going, and its report and sources once done.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['request_id'],
            'properties' => [
                ...$this->credentialFields(),
                'request_id' => Field::text('Request id', 'The id Start Research returned.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'request_id', 'The request id');
        $answer = $this->tavily($run, $config, 'GET', "/research/{$id}", query: ['include_usage' => 'true']);

        return ['id' => $answer['request_id'] ?? $id, 'status' => $answer['status'] ?? null, 'finished' => in_array($answer['status'] ?? null, ['completed', 'failed'], true), 'content' => $answer['content'] ?? null, 'sources' => $answer['sources'] ?? [], 'error' => $answer['error'] ?? null, 'credits' => data_get($answer, 'usage.credits')];
    }
}
