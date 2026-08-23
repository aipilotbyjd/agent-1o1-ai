<?php

namespace App\Nodes\Integrations\GoogleDocs;

use App\Models\Runs\Run;

class GoogleDocsGetDocumentNode extends AbstractGoogleDocsNode
{
    public function type(): string
    {
        return 'google_docs_get_document';
    }

    public function name(): string
    {
        return 'Google Docs: Get Document';
    }

    public function description(): string
    {
        return 'Fetches the contents of a Google Doc.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['document_id'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'document_id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, "/documents/{$config['document_id']}", $config);
    }
}
