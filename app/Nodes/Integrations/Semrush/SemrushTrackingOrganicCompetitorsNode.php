<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushTrackingOrganicCompetitorsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_tracking_organic_competitors';
    }

    public function name(): string
    {
        return 'Semrush: Tracking Organic Competitors';
    }

    public function description(): string
    {
        return 'The competitors found for a campaign\'s keywords in organic search.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'campaign_id' => $this->campaignField(),
                'url_type' => Field::advanced(Field::text('URL type', 'rootdomain, subdomain, subfolder or url.', 'rootdomain')),
                'url' => Field::advanced(Field::text('Tracked URL', 'With a mask: *.example.com/*. Separate several with a colon. Empty uses the campaign\'s.', '*.example.com/*')),
                'main_domain' => Field::advanced(Field::text('Main domain', 'Shown in a block of its own at the top. Without a mask.', 'example.com')),
                'black_list' => Field::advanced(Field::text('Exclude domains', 'Separated by |.', 'google.com|bing.com')),
                'top_start' => Field::advanced(Field::text('Top from', 'Position to start at.', '1')),
                'top_end' => Field::advanced(Field::text('Top to', 'Position to end at.', '10')),
                'date_begin' => Field::advanced(Field::text('From', 'As YYYYMMDD.', '20260101')),
                'date_end' => Field::advanced(Field::text('To', 'As YYYYMMDD.', '20260107')),
                'display_tags' => Field::advanced(Field::text('Tags', 'Separated by |. Put - before a tag to exclude it.', 'seo|-brand')),
                'display_tags_condition' => Field::advanced(Field::text('Tags condition', 'Tags joined by | (or) or & (and). Put ! before a tag to exclude it.', 'seo&!brand')),
                'display_limit' => Field::integer('Limit', 'The most rows to return.', 100, 1, 10000),
                'display_offset' => Field::advanced(Field::integer('Skip', 'Rows to skip first.', null, 0)),
                'display_sort' => Field::advanced(Field::text('Sort', 'Such as nq_desc, ph_asc or 0_pos_asc.', 'nq_desc')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['action' => 'report', 'type' => 'tracking_competitors_organic'];

        foreach (['url_type', 'url', 'main_domain', 'black_list', 'top_start', 'top_end', 'date_begin', 'date_end', 'display_tags', 'display_tags_condition', 'display_limit', 'display_offset', 'display_sort'] as $parameter) {
            $query[$parameter] = $config[$parameter] ?? null;
        }

        $answer = $this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->campaignId($config).'/tracking/', $query);
        $rows = array_values(array_filter((array) ($answer['data'] ?? []), 'is_array'));

        return [...array_diff_key($answer, ['data' => true]), 'rows' => $rows, 'count' => count($rows)];
    }
}
