<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomDeleteArticleNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_delete_article';
    }

    public function name(): string
    {
        return 'Intercom: Delete Article';
    }

    public function description(): string
    {
        return 'Deletes a Help Center article.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['article_id'],
            'properties' => [
                ...$this->credentialFields(),
                'article_id' => Field::text('Article id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'article_id', 'The article');
        $this->intercom($run, $config, 'DELETE', "/articles/{$id}");

        return ['deleted' => true, 'id' => $id];
    }
}
