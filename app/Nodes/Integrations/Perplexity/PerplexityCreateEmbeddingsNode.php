<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PerplexityCreateEmbeddingsNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_create_embeddings';
    }

    public function name(): string
    {
        return 'Perplexity: Create Embeddings';
    }

    public function description(): string
    {
        return 'Turns one or more texts into vectors, for search and comparison.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['input'],
            'properties' => [
                ...$this->credentialFields(),
                'input' => Field::list('Texts'),
                'model' => Field::select('Model', ['pplx-embed-v1-0.6b', 'pplx-embed-v1-4b'], null, 'pplx-embed-v1-0.6b'),
                'dimensions' => Field::advanced(Field::integer('Dimensions', null, null, 1)),
                'encoding_format' => Field::advanced(Field::select('Encoding', ['base64_int8', 'base64_binary'], null, 'base64_int8')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $input = $this->stringList($config['input'] ?? []);

        if ($input === []) {
            throw new InvalidArgumentException('At least one text is required.');
        }

        return $this->shapeEmbeddings($this->perplexity($run, $config, 'POST', '/v1/embeddings', $this->withoutEmpty(['input' => $input, 'model' => $this->textOf($config, 'model') ?? 'pplx-embed-v1-0.6b', 'dimensions' => $this->intOf($config, 'dimensions'), 'encoding_format' => $this->textOf($config, 'encoding_format')]), timeout: 60));
    }
}
