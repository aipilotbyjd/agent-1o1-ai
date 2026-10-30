<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogCheckSloDeletableNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_check_slo_deletable';
    }

    public function name(): string
    {
        return 'Datadog: Check SLO Can Be Deleted';
    }

    public function description(): string
    {
        return 'Says whether objectives can be deleted, or what still uses them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ids'],
            'properties' => [
                ...$this->credentialFields(),
                'ids' => Field::list('SLO ids'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $this->listOf($config, 'ids') ?: throw new InvalidArgumentException('At least one SLO id is required.');
        $response = $this->datadogResponse($run, $config, 'GET', '/api/v1/slo/can_delete', ['ids' => implode(',', $ids)]);

        if ($response->status() !== 409) {
            $this->assertOk($response);
        }

        return ['ok' => array_values((array) ($response->json('data.ok') ?? [])), 'errors' => (array) ($response->json('errors') ?? [])];
    }
}
