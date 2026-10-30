<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskUploadAttachmentNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_upload_attachment';
    }

    public function name(): string
    {
        return 'Zendesk: Upload Attachment';
    }

    public function description(): string
    {
        return 'Sends a file to Zendesk from its address and answers with a token to use on a comment. Attach File To Ticket does both steps.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_url'],
            'properties' => [
                ...$this->credentialFields(),
                'file_url' => Field::text('File address', 'A public http(s) address.', 'https://example.com/report.pdf'),
                'filename' => Field::advanced(Field::text('File name', 'Taken from the address when empty.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sendUpload($run, $config);
    }
}
