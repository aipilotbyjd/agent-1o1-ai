<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomFindCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_find_company';
    }

    public function name(): string
    {
        return 'Intercom: Find Company';
    }

    public function description(): string
    {
        return 'Finds a company by your own company id or its name.';
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
                'company_id' => Field::text('Your company id'),
                'name' => Field::text('Name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = $this->present(['company_id' => $this->text($config, 'company_id'), 'name' => $this->text($config, 'name')]);
        if ($query === []) {
            throw new \InvalidArgumentException('Give a company id or a name.');
        }
        $response = $this->intercomResponse($run, $config, 'GET', '/companies', $query);
        if ($response->status() === 404) {
            return ['found' => false];
        }
        $this->assertOk($response);
        $body = (array) $response->json();
        $company = isset($body['data']) ? (array) ($body['data'][0] ?? []) : $body;

        return filled($company['id'] ?? null) ? ['found' => true, ...$this->shapeCompany($company)] : ['found' => false];
    }
}
