<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListArticleCommentsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_article_comments';
    }

    public function name(): string
    {
        return 'Zendesk: List Article Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on an article. As a trigger it starts the flow for each new comment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['article_id'],
            'properties' => [
                ...$this->credentialFields(),
                'article_id' => Field::text('Article id'),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'article_id', 'The article');

        return $this->listing($run, $config, "/help_center/articles/{$id}/comments", 'comments');
    }

    protected function triggerItemsPath(): ?string
    {
        return 'comments';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 500];
    }

    public function triggerName(): string
    {
        return 'Zendesk: New article comment';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an article gets a comment. Checked every few minutes.';
    }
}
