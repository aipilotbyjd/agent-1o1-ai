<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListCompanySegmentsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_company_segments';
    }

    public function name(): string
    {
        return 'Intercom: List Company\'s Segments';
    }

    public function description(): string
    {
        return 'Lists the segments a company is in.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => [
                ...$this->credentialFields(),
                'id' => Field::text('Company id', 'Intercom\'s id of the company.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'id', 'The company');
        $rows = (array) ($this->intercom($run, $config, 'GET', "/companies/{$id}/segments")['data'] ?? []);

        return ['segments' => $rows, 'count' => count($rows)];
    }
}
