<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlStartAgentNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_start_agent';
    }

    public function name(): string
    {
        return 'Firecrawl: Start Agent';
    }

    public function description(): string
    {
        return 'Sends Firecrawl\'s agent to find data across the web from a description, and returns the job id at once. Read it with Get Agent Status, or start a flow with the Agent Finished trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['prompt'],
            'properties' => [
                ...$this->credentialFields(),
                'prompt' => Field::textarea('What to find'),
                'urls' => Field::list('Start from', 'Pages to look at first. Optional.'),
                'schema' => Field::json('Shape', 'A JSON schema for the result.'),
                'model' => Field::advanced(Field::select('Model', ['spark-2', 'spark-1-mini', 'spark-1-pro'], null, 'spark-2')),
                'effort' => Field::advanced(Field::select('Effort', ['low', 'medium', 'high'])),
                'max_credits' => Field::advanced(Field::integer('Max credits', null, null, 1)),
                'strict_urls' => Field::advanced(Field::boolean('Only the pages listed')),
                ...$this->webhookFields('started, action, completed, failed, cancelled'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urls = $this->stringList($config['urls'] ?? []);
        $answer = $this->firecrawl($run, $config, 'POST', '/agent', $this->withoutEmpty([
            'prompt' => $this->requiredString($config, 'prompt', 'What to find'),
            'urls' => array_map($this->checkedUrl(...), $urls),
            'schema' => $this->decoded($config['schema'] ?? null, 'The shape'),
            'model' => $this->textOf($config, 'model'),
            'effort' => $this->textOf($config, 'effort'),
            'maxCredits' => $this->intOf($config, 'max_credits'),
            'strictConstrainToURLs' => $this->boolOf($config, 'strict_urls'),
            'webhook' => $this->webhook($config),
        ]));

        return ['id' => $answer['id'] ?? null];
    }
}
