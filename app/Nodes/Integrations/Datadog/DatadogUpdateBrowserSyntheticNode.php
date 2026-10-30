<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateBrowserSyntheticNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_browser_synthetic';
    }

    public function name(): string
    {
        return 'Datadog: Update Browser Test';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on a Synthetic browser test; the rest stays as it is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['public_id'],
            'properties' => [
                ...$this->credentialFields(),
                'public_id' => $this->syntheticField(),
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

        $id = $this->pathId($config, 'public_id', 'The test');
        $current = $this->current($this->datadog($run, $config, 'GET', "/api/v1/synthetics/tests/browser/{$id}"));

        return $this->datadog($run, $config, 'PUT', "/api/v1/synthetics/tests/browser/{$id}", [], [...$current, ...$changes]);
    }

    /**
     * @param  array<mixed>  $test
     * @return array<string, mixed>
     */
    private function current(array $test): array
    {
        return array_intersect_key($test, array_flip(['name', 'type', 'subtype', 'config', 'locations', 'options', 'message', 'tags', 'status']));
    }
}
