<?php

namespace App\Nodes\Integrations\Sentry;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SentryListReleasesNode extends AbstractSentryNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'sentry_list_releases';
    }

    public function name(): string
    {
        return 'Sentry: List Releases';
    }

    public function description(): string
    {
        return 'Lists the releases of an organization, newest first. As a trigger it starts the flow for each new release. Checked every few minutes.';
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
                'organization' => $this->organizationField(),
                'query' => Field::text('Version contains'),
                'environment' => Field::text('Environment'),
                'project_ids' => Field::advanced(Field::list('Project ids', 'Numeric ids.')),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/releases/', 'releases', ['query' => $this->text($config, 'query'), 'project' => $this->items($config, 'project_ids'), 'environment' => $this->text($config, 'environment'), 'per_page' => $this->limit($config)], $this->shapeRelease(...));
    }

    public function triggerName(): string
    {
        return 'Sentry: New release';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a release is created in Sentry. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'releases';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['version'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100, 'cursor' => null];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
