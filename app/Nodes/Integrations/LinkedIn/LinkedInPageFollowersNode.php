<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinkedInPageFollowersNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_page_followers';
    }

    public function name(): string
    {
        return 'LinkedIn: Get Page Follower Count';
    }

    public function description(): string
    {
        return 'Reads how many people follow a company page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page'],
            'properties' => [
                ...$this->credentialFields(),
                'page' => Field::dynamic('Company page', 'linkedin.organizations', null, 'Acme Inc'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->authorUrn($run, $config, 'page');

        if (! str_starts_with($page, 'urn:li:organization:')) {
            throw new InvalidArgumentException('Choose a company page.');
        }

        $size = $this->linkedin($run, $config, 'GET', '/rest/networkSizes/'.rawurlencode($page), ['edgeType' => 'COMPANY_FOLLOWED_BY_MEMBER']);

        return ['page' => $page, 'followers' => isset($size['firstDegreeSize']) ? (int) $size['firstDegreeSize'] : null];
    }
}
