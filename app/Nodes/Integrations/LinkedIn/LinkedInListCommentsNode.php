<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class LinkedInListCommentsNode extends AbstractLinkedInNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'linkedin_list_comments';
    }

    public function name(): string
    {
        return 'LinkedIn: List Comments';
    }

    public function description(): string
    {
        return 'Lists the comments on a post. As a trigger it starts the flow for each new one. Checked every few minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['post_id'],
            'properties' => [
                ...$this->credentialFields(),
                'post_id' => Field::text('Post', 'The post URN from a previous step, e.g. urn:li:share:123.'),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urn = $this->postUrn($config);
        $comments = array_values(array_filter((array) ($this->linkedin($run, $config, 'GET', '/rest/socialActions/'.rawurlencode($urn).'/comments', ['count' => $this->limit($config, 50)])['elements'] ?? []), 'is_array'));

        usort($comments, fn (array $a, array $b): int => (int) ($b['created']['time'] ?? 0) <=> (int) ($a['created']['time'] ?? 0));

        return ['comments' => array_map($this->shapeComment(...), $comments), 'count' => count($comments)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'comments';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'LinkedIn: New comment';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone comments on a LinkedIn post. Checked every few minutes.';
    }
}
