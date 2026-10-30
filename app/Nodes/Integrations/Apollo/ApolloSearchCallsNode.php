<?php

namespace App\Nodes\Integrations\Apollo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ApolloSearchCallsNode extends AbstractApolloNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'apollo_search_calls';
    }

    public function name(): string
    {
        return 'Apollo: Search Calls';
    }

    public function description(): string
    {
        return 'Finds call records by date, length, user and keywords. As a trigger it starts the flow for each new one.';
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
                'date_from' => Field::text('From', 'YYYY-MM-DD.'),
                'date_to' => Field::text('To', 'YYYY-MM-DD.'),
                'q_keywords' => Field::text('Keywords'),
                'user_ids' => Field::advanced(Field::list('User ids')),
                'inbound' => Field::advanced(Field::select('Direction', ['incoming' => 'Incoming', 'outgoing' => 'Outgoing'])),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['date_range[min]' => $config['date_from'] ?? null, 'date_range[max]' => $config['date_to'] ?? null, 'user_ids' => $this->stringList($config, 'user_ids'), ...$this->only($config, ['q_keywords', 'inbound']), ...$this->paging($config)];

        return $this->listResult($this->apollo($run, $config, 'GET', '/phone_calls/search', $query), 'phone_calls');
    }

    public function triggerName(): string
    {
        return 'Apollo: New call';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a call record appears in Apollo. Checks every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'phone_calls';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => self::MAX_PAGE_SIZE];
    }
}
