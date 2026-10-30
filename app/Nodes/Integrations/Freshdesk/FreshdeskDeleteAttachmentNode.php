<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteAttachmentNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_attachment';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Attachment';
    }

    public function description(): string
    {
        return 'Deletes an attachment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/attachments/{$id}", $id);
    }
}
