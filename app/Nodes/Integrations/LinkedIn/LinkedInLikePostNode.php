<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInLikePostNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_like_post';
    }

    public function name(): string
    {
        return 'LinkedIn: Like Post';
    }

    public function description(): string
    {
        return 'Reacts to a post as you or as a company page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['post_id'],
            'properties' => [
                ...$this->credentialFields(),
                'post_id' => Field::text('Post', 'The post URN from a previous step, e.g. urn:li:share:123.'),
                'reaction' => Field::select('Reaction', ['LIKE' => 'Like', 'PRAISE' => 'Celebrate', 'EMPATHY' => 'Love', 'INTEREST' => 'Insightful', 'APPRECIATION' => 'Support', 'ENTERTAINMENT' => 'Funny'], null, 'LIKE'),
                'author' => $this->authorField('React as'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urn = $this->postUrn($config);
        $actor = $this->authorUrn($run, $config);
        $reaction = array_key_exists($config['reaction'] ?? 'LIKE', ['LIKE' => 1, 'PRAISE' => 1, 'EMPATHY' => 1, 'INTEREST' => 1, 'APPRECIATION' => 1, 'ENTERTAINMENT' => 1]) ? ($config['reaction'] ?? 'LIKE') : 'LIKE';

        $this->linkedin($run, $config, 'POST', '/rest/reactions', ['actor' => $actor], ['root' => $urn, 'reactionType' => $reaction]);

        return ['reacted' => true, 'post' => $urn, 'reaction' => $reaction];
    }
}
