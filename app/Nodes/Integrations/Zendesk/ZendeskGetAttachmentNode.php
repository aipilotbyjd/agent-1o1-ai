<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskGetAttachmentNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_get_attachment';
    }

    public function name(): string
    {
        return 'Zendesk: Get Attachment';
    }

    public function description(): string
    {
        return 'Reads an attachment\'s name, size and download address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['attachment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'attachment_id' => Field::text('Attachment id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'attachment_id', 'The attachment');

        return $this->findOne($run, $config, "/attachments/{$id}", 'attachment', $id);
    }
}
