<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The latest posts of a member or a company page, as a reader and as a trigger.
 * LinkedIn has no push for posts, so a poll reads the newest page and the poll's
 * own dedupe by post URN decides what runs. Reading a member's posts needs the
 * `r_member_social` scope (which LinkedIn grants only to approved apps), a page's
 * `r_organization_social`.
 */
class LinkedInListPostsNode extends AbstractLinkedInNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'linkedin_list_posts';
    }

    public function name(): string
    {
        return 'LinkedIn: List Posts';
    }

    public function description(): string
    {
        return 'Lists the latest posts of you or a company page, newest first. As a trigger it starts the flow for each new one. Checked every few minutes.';
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
                'author' => $this->authorField('Posts of'),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $posts = array_values(array_filter((array) ($this->linkedin($run, $config, 'GET', '/rest/posts', [
            'q' => 'author',
            'author' => $this->authorUrn($run, $config),
            'count' => $this->limit($config, 25),
            'sortBy' => 'LAST_MODIFIED',
        ])['elements'] ?? []), 'is_array'));

        usort($posts, fn (array $a, array $b): int => (int) ($b['createdAt'] ?? 0) <=> (int) ($a['createdAt'] ?? 0));

        return ['posts' => array_map($this->shapePost(...), $posts), 'count' => count($posts)];
    }

    public function triggerName(): string
    {
        return 'LinkedIn: New post';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when you or a company page publish a new LinkedIn post. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'posts';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 50];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
