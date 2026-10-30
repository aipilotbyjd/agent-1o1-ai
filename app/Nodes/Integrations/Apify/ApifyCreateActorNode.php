<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyCreateActorNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_create_actor';
    }

    public function name(): string
    {
        return 'Apify: Create Actor';
    }

    public function description(): string
    {
        return 'Creates a private Actor, optionally with its source code in a version.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', 'Lower case letters, digits and -.', 'my-actor'),
                'title' => Field::text('Title'),
                'description' => Field::advanced(Field::textarea('Description')),
                'versions' => Field::json('Versions', 'A list of versions with their source files, as in Apify\'s docs.', 'array'),
                'default_run_options' => Field::advanced(Field::json('Default run options')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['actor' => $this->apify($run, $config, 'POST', '/actors', $this->withoutEmpty(['name' => $this->requiredString($config, 'name', 'The name'), 'title' => $this->textOf($config, 'title'), 'description' => $this->textOf($config, 'description'), 'versions' => $this->decoded($config['versions'] ?? null, 'The versions'), 'defaultRunOptions' => $this->decoded($config['default_run_options'] ?? null, 'The default run options')]))];
    }
}
