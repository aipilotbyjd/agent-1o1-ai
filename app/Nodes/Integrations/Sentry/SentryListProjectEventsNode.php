<?php

namespace App\Nodes\Integrations\Sentry;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SentryListProjectEventsNode extends AbstractSentryNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'sentry_list_project_events';
    }

    public function name(): string
    {
        return 'Sentry: List Project Events';
    }

    public function description(): string
    {
        return 'Lists a project\'s error events, newest first. As a trigger it starts the flow for each new event. Checked every few minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'stats_period' => Field::text('Period', 'Like 24h or 14d.'),
                'full' => Field::advanced(Field::boolean('Include the full event')),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/events/', 'events', ['statsPeriod' => $this->text($config, 'stats_period'), 'full' => ($config['full'] ?? false) ? 'true' : null], $this->shapeEvent(...));
    }

    public function triggerName(): string
    {
        return 'Sentry: New event';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow for each new error event in a Sentry project. Checked every few minutes; a busy project may have more events than one check can read.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'cursor' => null];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
