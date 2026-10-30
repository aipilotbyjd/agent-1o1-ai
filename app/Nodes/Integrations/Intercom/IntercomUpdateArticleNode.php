<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomUpdateArticleNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_update_article';
    }

    public function name(): string
    {
        return 'Intercom: Update Article';
    }

    public function description(): string
    {
        return 'Changes a Help Center article. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['article_id'],
            'properties' => [
                ...$this->credentialFields(),
                'article_id' => Field::text('Article id'),
                'title' => Field::advanced(Field::text('Title')),
                'body' => Field::advanced(Field::textarea('Body', 'HTML is kept.')),
                'description' => Field::advanced(Field::text('Description')),
                'state' => Field::advanced(Field::select('State', ['draft' => 'Draft', 'published' => 'Published'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'article_id', 'The article');
        $body = $this->present(['title' => $this->text($config, 'title'), 'body' => $this->text($config, 'body'), 'description' => $this->text($config, 'description'), 'state' => $this->text($config, 'state')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->intercom($run, $config, 'PUT', "/articles/{$id}", [], $body);
    }
}
