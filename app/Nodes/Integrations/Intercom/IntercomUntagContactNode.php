<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUntagContactNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_untag_contact';
    }

    public function name(): string
    {
        return 'Intercom: Remove Tag From Contact';
    }

    public function description(): string
    {
        return 'Takes a tag off a contact.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id', 'tag_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
                'tag_id' => Field::dynamic('Tag', 'intercom.tags', null, 'VIP'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');
        $tag = $this->safeId($config, 'tag_id', 'The tag');

        return $this->intercom($run, $config, 'DELETE', "/contacts/{$id}/tags/{$tag}");
    }
}
