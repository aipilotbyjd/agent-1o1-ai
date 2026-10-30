<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class LinkedInListPagesNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_list_pages';
    }

    public function name(): string
    {
        return 'LinkedIn: List My Pages';
    }

    public function description(): string
    {
        return 'Lists the company pages you administer, with the ids to post as.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $roles = (array) ($this->linkedin($run, $config, 'GET', '/rest/organizationAcls', ['q' => 'roleAssignee', 'role' => 'ADMINISTRATOR', 'state' => 'APPROVED', 'count' => 20])['elements'] ?? []);
        $pages = [];

        foreach (array_slice($roles, 0, 20) as $role) {
            $urn = (string) ($role['organization'] ?? '');

            if (preg_match('/^urn:li:organization:(\d+)$/', $urn, $match) !== 1) {
                continue;
            }

            $response = $this->linkedinResponse($run, $config, 'GET', '/rest/organizations/'.$match[1]);

            $pages[] = ['id' => $match[1], 'urn' => $urn, 'name' => $response->successful() ? $response->json('localizedName') : null, 'vanity_name' => $response->successful() ? $response->json('vanityName') : null];
        }

        return ['pages' => $pages, 'count' => count($pages)];
    }
}
