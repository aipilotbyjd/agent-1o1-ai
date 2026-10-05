<?php

namespace App\Nodes\Integrations\GoogleDocs;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

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

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title', null, 'Meeting notes'),
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
