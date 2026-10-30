<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryBulkDeleteIssuesNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_bulk_delete_issues';
    }

    public function name(): string
    {
        return 'Sentry: Delete Issues In Bulk';
    }

    public function description(): string
    {
        return 'Removes several issues at once, by their numeric ids.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'issue_ids' => Field::list('Issue ids', 'Numeric ids.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ids = $this->items($config, 'issue_ids');

        if ($ids === [] || array_filter($ids, fn (string $id): bool => ! ctype_digit($id)) !== []) {
            throw new InvalidArgumentException('Issue ids must be numbers.');
        }

        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config).'/issues/', ['id' => $ids]);

        return ['deleted' => true, 'ids' => $ids];
    }
}
