<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApifyUpdateActorNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_update_actor';
    }

    public function name(): string
    {
        return 'Apify: Update Actor';
    }

    public function description(): string
    {
        return 'Changes an Actor\'s name, title or description. Empty settings stay as they are.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['actor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'actor_id' => $this->actorField(),
                'name' => Field::text('Name'),
                'title' => Field::text('Title'),
                'description' => Field::advanced(Field::textarea('Description')),
                'default_run_options' => Field::advanced(Field::json('Default run options')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['actor' => $this->apify($run, $config, 'PUT', '/actors/'.$this->actorId($config), $this->withoutEmpty(['name' => $this->textOf($config, 'name'), 'title' => $this->textOf($config, 'title'), 'description' => $this->textOf($config, 'description'), 'defaultRunOptions' => $this->decoded($config['default_run_options'] ?? null, 'The default run options')]))];
    }
}
