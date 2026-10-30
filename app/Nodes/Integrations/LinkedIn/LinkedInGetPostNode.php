<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInGetPostNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_get_post';
    }

    public function name(): string
    {
        return 'LinkedIn: Get Post';
    }

    public function description(): string
    {
        return 'Reads a post. Says whether it exists.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $urn = $this->postUrn($config);
        $response = $this->linkedinResponse($run, $config, 'GET', '/rest/posts/'.rawurlencode($urn));

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $urn];
        }

        $this->assertOk($response);

        return [...$this->shapePost((array) $response->json()), 'found' => true];
    }
}
