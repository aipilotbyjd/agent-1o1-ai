<?php

namespace App\Nodes\Integrations\GoogleDocs;

use App\Models\Runs\Run;

class GoogleDocsAppendTextNode extends AbstractGoogleDocsNode
{
    public function type(): string
    {
        return 'google_docs_append_text';
    }

    public function name(): string
    {
        return 'Google Docs: Append Text';
    }

    public function description(): string
    {
        return 'Appends text to the end of a Google Doc.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['document_id', 'text'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'document_id' => ['type' => 'string'],
                'text' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, "/documents/{$config['document_id']}:batchUpdate", $config, [
            'requests' => [
                [
                    'insertText' => [
                        'endOfSegmentLocation' => ['segmentId' => ''],
                        'text' => $config['text'],
                    ],
                ],
            ],
        ]);
    }
}
