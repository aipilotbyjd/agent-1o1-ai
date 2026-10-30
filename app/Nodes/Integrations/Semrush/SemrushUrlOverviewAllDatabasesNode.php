<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushUrlOverviewAllDatabasesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_url_overview_all_databases';
    }

    public function name(): string
    {
        return 'Semrush: URL Overview (All Databases)';
    }

    public function description(): string
    {
        return 'Organic and paid rankings and traffic of a URL in every regional database.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('URL', null, 'https://example.com/page'),
                'database' => Field::advanced($this->databaseField()),
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'date' => $this->dateField(),
                'sort' => $this->sortField(['rk_asc', 'rk_desc', 'or_asc', 'or_desc', 'ot_asc', 'ot_desc', 'oc_asc', 'oc_desc', 'ad_asc', 'ad_desc', 'at_asc', 'at_desc', 'ac_asc', 'ac_desc']),
                'columns' => $this->columnsField(['Db', 'Dt', 'Dn', 'Rk', 'Or', 'Ot', 'Oc', 'Ad', 'At', 'Ac', 'Sh', 'Sv', 'FKn', 'FPn', 'Sr', 'Srb', 'St', 'Stb', 'Sc', 'Srn', 'Srl']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'url_ranks', 'Db,Rk,Dn,Or,Ot,Oc,Ad,At,Ac,Sh,Sv', ['url' => $this->requiredString($config, 'url', 'The url')]);
    }
}
