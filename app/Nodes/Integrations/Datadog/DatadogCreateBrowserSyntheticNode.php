<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogCreateBrowserSyntheticNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_browser_synthetic';
    }

    public function name(): string
    {
        return 'Datadog: Create Browser Test';
    }

    public function description(): string
    {
        return 'Creates a Synthetic browser test.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'locations', 'config', 'options'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                'locations' => Field::list('Locations', 'Like aws:eu-central-1.'),
                'config' => Field::json('Test definition', 'The request and assertions, as in the Datadog API.'),
                'options' => Field::json('Options', 'Like {"tick_every":300}.'),
                'message' => Field::textarea('Notification message'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'status' => Field::advanced(Field::select('Status', ['live' => 'Live', 'paused' => 'Paused'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $changes = array_filter([
            'name' => $this->text($config, 'name'),
            'locations' => $this->listOf($config, 'locations') ?: null,
            'config' => $this->jsonOf($config, 'config', 'Test definition'),
            'options' => $this->jsonOf($config, 'options', 'Options'),
            'message' => $this->text($config, 'message'),
            'tags' => $this->listOf($config, 'tags') ?: null,
            'subtype' => $this->text($config, 'subtype'),
            'status' => $this->text($config, 'status'),
        ], fn (mixed $value): bool => $value !== null);

        foreach (['name', 'locations', 'config', 'options'] as $required) {
            if (! isset($changes[$required])) {
                throw new InvalidArgumentException("The {$required} setting is required.");
            }
        }

        return $this->datadog($run, $config, 'POST', '/api/v1/synthetics/tests/browser', [], ['type' => 'browser', 'message' => '', ...$changes]);
    }
}
