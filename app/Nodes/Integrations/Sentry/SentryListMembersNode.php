<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListMembersNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_members';
    }

    public function name(): string
    {
        return 'Sentry: List Members';
    }

    public function description(): string
    {
        return 'Lists the members of an organization.';
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
                'query' => Field::text('Search', 'Like email:name@example.com.'),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/members/', 'members', ['query' => $this->text($config, 'query')], $this->shapeMember(...));
    }
}
