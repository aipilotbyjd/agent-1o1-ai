<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomTagCompanyNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_tag_company';
    }

    public function name(): string
    {
        return 'Intercom: Add or Remove Tag On Company';
    }

    public function description(): string
    {
        return 'Puts a tag on a company, or takes it off.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['tag_name', 'id'],
            'properties' => [
                ...$this->credentialFields(),
                'tag_name' => Field::text('Tag name', null, 'VIP'),
                'id' => Field::text('Company id', 'Intercom\'s id of the company.'),
                'untag' => Field::advanced(Field::boolean('Remove the tag')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $company = $this->present(['id' => $this->safeId($config, 'id', 'The company'), 'untag' => filter_var($config['untag'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null]);

        return $this->intercom($run, $config, 'POST', '/tags', [], ['name' => $this->requiredString($config, 'tag_name', 'The tag name'), 'companies' => [$company]]);
    }
}
