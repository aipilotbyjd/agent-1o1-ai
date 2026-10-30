<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsUpdateLeadFormNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_update_lead_form';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Update Lead Form';
    }

    public function description(): string
    {
        return 'Changes a lead form\'s name, state or content, or archives it. A published form can only change a few things.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => Field::text('Form id'),
                'name' => Field::text('Name'),
                'state' => Field::select('State', ['PUBLISHED' => 'Published', 'ARCHIVED' => 'Archived']),
                'content' => Field::advanced(Field::json('Content', 'LinkedIn\'s form content, for a form that is not published yet.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'form_id', 'The form');
        $set = $this->only($config, ['name', 'state']);

        if (filled($config['content'] ?? null)) {
            $set['content'] = $this->jsonSetting($config, 'content', 'The content', true);
        }

        $this->patch($run, $config, "/leadForms/{$id}", $set);

        return ['updated' => true, 'id' => $id];
    }
}
