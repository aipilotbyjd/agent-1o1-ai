<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomFindOrCreateCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_find_or_create_company';
    }

    public function name(): string
    {
        return 'Intercom: Find or Create Company';
    }

    public function description(): string
    {
        return 'Finds the company with this company id, or creates it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['company_id'],
            'properties' => [
                ...$this->credentialFields(),
                'company_id' => Field::text('Your company id'),
                'name' => Field::text('Name', 'Used when it is new.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $companyId = $this->requiredString($config, 'company_id', 'The company id');
        $response = $this->intercomResponse($run, $config, 'GET', '/companies', ['company_id' => $companyId]);
        if ($response->successful() && filled($response->json('id'))) {
            return ['created' => false, ...$this->shapeCompany((array) $response->json())];
        }
        if ($response->status() !== 404) {
            $this->assertOk($response);
        }

        return ['created' => true, ...$this->shapeCompany($this->intercom($run, $config, 'POST', '/companies', [], $this->present(['company_id' => $companyId, 'name' => $this->text($config, 'name')])))];
    }
}
