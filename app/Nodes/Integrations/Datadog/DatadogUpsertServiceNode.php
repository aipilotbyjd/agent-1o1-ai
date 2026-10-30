<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpsertServiceNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_upsert_service';
    }

    public function name(): string
    {
        return 'Datadog: Create Or Update Service';
    }

    public function description(): string
    {
        return 'Creates a service definition in the Service Catalog, or replaces it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'service' => Field::text('Service', null, 'web'),
                'team' => Field::text('Team'),
                'description' => Field::textarea('Description'),
                'tier' => Field::advanced(Field::text('Tier', null, 'tier1')),
                'tags' => Field::advanced(Field::list('Tags')),
                'definition' => Field::advanced(Field::json('Full definition', 'As in the API. Used instead of the fields above.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $definition = $this->jsonOf($config, 'definition', 'Definition') ?? array_filter(['schema-version' => 'v2.2', 'dd-service' => $this->requiredString($config, 'service', 'The service'), 'team' => $this->text($config, 'team'), 'description' => $this->text($config, 'description'), 'tier' => $this->text($config, 'tier'), 'tags' => $this->listOf($config, 'tags') ?: null], fn (mixed $value): bool => $value !== null);
        $services = $this->records($this->datadog($run, $config, 'POST', '/api/v2/services/definitions', [], $definition));

        return ['services' => $services, 'count' => count($services)];
    }
}
