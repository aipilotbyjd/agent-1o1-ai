<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInAdsSearchAccountsNode extends AbstractLinkedInAdsNode
{
    public function type(): string
    {
        return 'linkedin_ads_search_accounts';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: Search Ad Accounts';
    }

    public function description(): string
    {
        return 'Finds ad accounts by status, type, name or id.';
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
                'status' => Field::select('Status', ['ACTIVE', 'DRAFT', 'CANCELED', 'PENDING_DELETION', 'REMOVED']),
                'name' => Field::text('Name'),
                'type' => Field::advanced(Field::select('Type', ['BUSINESS', 'ENTERPRISE'])),
                'test' => Field::advanced(Field::select('Test accounts', ['true' => 'Only test accounts', 'false' => 'Only real accounts'])),
                ...$this->pageSizeField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $test = filled($config['test'] ?? null) ? $config['test'] === 'true' : null;
        $query = 'q=search&'.self::searchValue(['status' => $this->stringList($config, 'status'), 'name' => $this->stringList($config, 'name'), 'type' => $this->stringList($config, 'type')], $test).'sortOrder=DESCENDING&'.$this->pageQuery($config);

        return $this->elements($this->linkedin($run, $config, 'GET', '/adAccounts', $query), 'accounts');
    }
}
