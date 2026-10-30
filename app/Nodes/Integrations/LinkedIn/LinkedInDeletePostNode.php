<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInDeletePostNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_delete_post';
    }

    public function name(): string
    {
        return 'LinkedIn: Delete Post';
    }

    public function description(): string
    {
        return 'Deletes a post you or your page made.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['post_id'],
            'properties' => [
                ...$this->credentialFields(),
                'post_id' => Field::text('Post', 'The post URN from a previous step, e.g. urn:li:share:123.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urn = $this->postUrn($config);
        $response = $this->linkedinResponse($run, $config, 'DELETE', '/rest/posts/'.rawurlencode($urn));

        if ($response->status() === 404) {
            return ['deleted' => false, 'id' => $urn];
        }

        $this->assertOk($response);

        return ['deleted' => true, 'id' => $urn];
    }
}
