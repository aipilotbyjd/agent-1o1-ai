<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListArticlesNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_articles';
    }

    public function name(): string
    {
        return 'Zendesk: List Articles';
    }

    public function description(): string
    {
        return 'Lists the Help Center\'s articles, newest first. As a trigger it starts the flow when an article is added.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/help_center/articles', 'articles', ['sort_by' => 'created_at', 'sort_order' => 'desc']);
    }

    protected function triggerItemsPath(): ?string
    {
        return 'articles';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Zendesk: New article';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Help Center article is added. Checked every few minutes.';
    }
}
