<?php

namespace App\Nodes\Integrations\GoogleDocs;

use App\Models\Runs\Run;

class GoogleDocsCreateDocumentNode extends AbstractGoogleDocsNode
{
    public function type(): string
    {
        return 'google_docs_create_document';
    }

    public function name(): string
    {
        return 'Google Docs: Create Document';
    }

    public function description(): string
    {
        return 'Creates a new Google Doc.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/documents', $config, [
            'title' => $config['title'],
        ]);
    }
}
