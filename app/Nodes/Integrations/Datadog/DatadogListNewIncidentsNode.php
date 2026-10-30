<?php

namespace App\Nodes\Integrations\Datadog;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class DatadogListNewIncidentsNode extends AbstractDatadogNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'datadog_list_new_incidents';
    }

    public function name(): string
    {
        return 'Datadog: List New Incidents';
    }

    public function description(): string
    {
        return 'Lists incidents, newest first. As a trigger it starts the flow when an incident is declared. Checked every few minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Query', 'Default every incident.', 'severity:SEV-1'),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = $this->text($config, 'query') ?? 'state:(active OR stable OR resolved)';
        $result = $this->datadog($run, $config, 'GET', '/api/v2/incidents/search', ['query' => $query, 'sort' => '-created', 'page[size]' => $this->limit($config, 50, 100)]);
        $incidents = array_map(fn (mixed $row): array => $this->shapeIncident((array) data_get($row, 'data', [])), array_values((array) data_get($result, 'data.attributes.incidents', [])));

        return ['incidents' => $incidents, 'count' => count($incidents)];
    }

    public function triggerName(): string
    {
        return 'Datadog: New incident';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an incident is declared in Datadog. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'incidents';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
