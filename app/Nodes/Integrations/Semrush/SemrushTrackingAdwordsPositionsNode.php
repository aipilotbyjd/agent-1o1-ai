<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushTrackingAdwordsPositionsNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_tracking_adwords_positions';
    }

    public function name(): string
    {
        return 'Semrush: Tracking Paid Positions';
    }

    public function description(): string
    {
        return 'The paid position of each tracked keyword.';
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
                'url' => Field::advanced(Field::text('Tracked URL', 'With a mask: *.example.com/*. Separate several with a colon. Empty uses the campaign\'s.', '*.example.com/*')),
                'date_begin' => Field::advanced(Field::text('From', 'As YYYYMMDD.', '20260101')),
                'date_end' => Field::advanced(Field::text('To', 'As YYYYMMDD.', '20260107')),
                'display_tags' => Field::advanced(Field::text('Tags', 'Separated by |. Put - before a tag to exclude it.', 'seo|-brand')),
                'display_tags_condition' => Field::advanced(Field::text('Tags condition', 'Tags joined by | (or) or & (and). Put ! before a tag to exclude it.', 'seo&!brand')),
                'display_filter' => Field::advanced(Field::text('Filter', 'In Semrush\'s form, on Ph, Nq or Cp.', '+|Ph|Co|seo')),
                'display_limit' => Field::integer('Limit', 'The most rows to return.', 100, 1, 10000),
                'display_offset' => Field::advanced(Field::integer('Skip', 'Rows to skip first.', null, 0)),
                'display_sort' => Field::advanced(Field::text('Sort', 'Such as nq_desc, ph_asc or 0_pos_asc.', 'nq_desc')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['action' => 'report', 'type' => 'tracking_position_adwords'];

        foreach (['url', 'date_begin', 'date_end', 'display_tags', 'display_tags_condition', 'display_filter', 'display_limit', 'display_offset', 'display_sort'] as $parameter) {
            $query[$parameter] = $config[$parameter] ?? null;
        }

        $answer = $this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->campaignId($config).'/tracking/', $query);
        $rows = array_values(array_filter((array) ($answer['data'] ?? []), 'is_array'));

        return [...array_diff_key($answer, ['data' => true]), 'rows' => $rows, 'count' => count($rows)];
    }
}
