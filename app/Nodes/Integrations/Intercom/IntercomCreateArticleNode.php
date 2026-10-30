<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomCreateArticleNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_create_article';
    }

    public function name(): string
    {
        return 'Intercom: Create Article';
    }

    public function description(): string
    {
        return 'Writes a Help Center article, as a draft unless published.';
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
                'title' => Field::text('Title'),
                'body' => Field::textarea('Body', 'HTML is kept.'),
                'description' => Field::advanced(Field::text('Description')),
                'state' => Field::advanced(Field::select('State', ['draft' => 'Draft', 'published' => 'Published'], null, 'draft')),
                'author_id' => Field::dynamic('Author', 'intercom.admins'),
                'parent_id' => Field::advanced(Field::text('Collection id')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $author = $this->text($config, 'author_id') ?? $this->adminId($run, $config);

        return $this->intercom($run, $config, 'POST', '/articles', [], $this->present(['title' => $this->requiredString($config, 'title', 'The title'), 'body' => $this->text($config, 'body'), 'description' => $this->text($config, 'description'), 'state' => ($config['state'] ?? 'draft') === 'published' ? 'published' : 'draft', 'author_id' => (int) $author, 'parent_id' => filled($config['parent_id'] ?? null) ? (int) $config['parent_id'] : null]));
    }
}
