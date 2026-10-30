<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PerplexityCreateContextualizedEmbeddingsNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_create_contextualized_embeddings';
    }

    public function name(): string
    {
        return 'Perplexity: Create Contextualized Embeddings';
    }

    public function description(): string
    {
        return 'Turns document chunks into vectors that share each document\'s context.';
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
                'input' => Field::json('Documents', 'A list of documents, each a list of its chunks of text.', 'array'),
                'model' => Field::select('Model', ['pplx-embed-context-v1-0.6b', 'pplx-embed-context-v1-4b'], null, 'pplx-embed-context-v1-0.6b'),
                'dimensions' => Field::advanced(Field::integer('Dimensions', null, null, 1)),
                'encoding_format' => Field::advanced(Field::select('Encoding', ['base64_int8', 'base64_binary'], null, 'base64_int8')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $input = $this->decoded($config['input'] ?? null, 'The documents');

        if ($input === null) {
            throw new InvalidArgumentException('At least one document is required.');
        }

        return $this->shapeEmbeddings($this->perplexity($run, $config, 'POST', '/v1/contextualizedembeddings', $this->withoutEmpty(['input' => $input, 'model' => $this->textOf($config, 'model') ?? 'pplx-embed-context-v1-0.6b', 'dimensions' => $this->intOf($config, 'dimensions'), 'encoding_format' => $this->textOf($config, 'encoding_format')]), timeout: 60));
    }
}
