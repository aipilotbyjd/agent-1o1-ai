<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInCommentOnPostNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_comment_on_post';
    }

    public function name(): string
    {
        return 'LinkedIn: Comment on Post';
    }

    public function description(): string
    {
        return 'Adds a comment to a post, as you or as a company page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['post_id', 'text'],
            'properties' => [
                ...$this->credentialFields(),
                'post_id' => Field::text('Post', 'The post URN from a previous step, e.g. urn:li:share:123.'),
                'text' => Field::textarea('Comment'),
                'author' => $this->authorField('Comment as'),
                'markup' => Field::advanced(Field::boolean('The text is already LinkedIn markup', 'Off escapes special characters such as ( ) # @ so the text shows as typed.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urn = $this->postUrn($config);
        $response = $this->linkedinResponse($run, $config, 'POST', '/rest/socialActions/'.rawurlencode($urn).'/comments', [], [
            'actor' => $this->authorUrn($run, $config),
            'message' => ['text' => $this->commentary($config)],
        ]);

        $this->assertOk($response);

        return ['commented' => true, 'post' => $urn, 'id' => $response->json('id') ?? $response->json('$URN') ?? ($response->header('x-restli-id') ?: null)];
    }
}
