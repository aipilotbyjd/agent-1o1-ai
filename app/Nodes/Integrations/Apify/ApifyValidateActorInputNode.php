<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyValidateActorInputNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_validate_actor_input';
    }

    public function name(): string
    {
        return 'Apify: Validate Actor Input';
    }

    public function description(): string
    {
        return 'Checks an input against an Actor\'s input schema, without running it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['actor_id', 'input'],
            'properties' => [
                ...$this->credentialFields(),
                'actor_id' => $this->actorField(),
                'input' => Field::json('Input'),
                'build' => Field::advanced(Field::text('Build', null, 'latest')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->apifyResponse($run, $config, 'POST', '/actors/'.$this->actorId($config).'/validate-input', $this->decoded($config['input'] ?? null, 'The input') ?? [], ['build' => $this->textOf($config, 'build')]);

        return ['valid' => (bool) $response->json('valid')];
    }
}
