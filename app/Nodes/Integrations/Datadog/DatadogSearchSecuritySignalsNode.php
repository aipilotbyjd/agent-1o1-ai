<?php

namespace App\Nodes\Integrations\Datadog;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

class DatadogSearchSecuritySignalsNode extends AbstractDatadogNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'datadog_search_security_signals';
    }

    public function name(): string
    {
        return 'Datadog: Search Security Signals';
    }

    public function description(): string
    {
        return 'Searches security signals by query and time range. As a trigger it starts the flow for each new one. Checked every few minutes.';
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
                ...$this->searchFields('Datadog signal search, like security:attack status:high.', 'now-1h'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->searchBody($config, [], 'now-1h');
        $body['filter']['from'] = $this->isoTime($body['filter']['from']);
        $body['filter']['to'] = $this->isoTime($body['filter']['to']);
        $result = $this->datadog($run, $config, 'POST', '/api/v2/security_monitoring/signals/search', [], $body);
        $signals = array_values(array_filter((array) ($result['data'] ?? []), 'is_array'));

        return ['signals' => array_map($this->shapeSignal(...), $signals), 'count' => count($signals), 'next_cursor' => data_get($result, 'meta.page.after')];
    }

    public function triggerName(): string
    {
        return 'Datadog: New security signal';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a security signal matching the query is raised in Datadog. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'signals';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100, 'cursor' => null, 'from' => 'now-1h', 'to' => 'now', 'sort' => '-timestamp'];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * This endpoint takes full dates, not `now-1h`.
     */
    private function isoTime(string $value): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $this->unixTime($value, 'The time'));
    }
}
