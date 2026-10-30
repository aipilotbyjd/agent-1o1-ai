<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloListJobPostingsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_list_job_postings';
    }

    public function name(): string
    {
        return 'Apollo: List Job Postings';
    }

    public function description(): string
    {
        return 'Lists the current job postings of a company: a sign of what it is buying or building. Uses Apollo credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['organization_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization_id' => Field::text('Organization id'),
                ...$this->pagingFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listResult($this->apollo($run, $config, 'GET', '/organizations/'.$this->recordId($config, 'organization_id', 'The organization id').'/job_postings', $this->paging($config)), 'organization_job_postings');
    }
}
