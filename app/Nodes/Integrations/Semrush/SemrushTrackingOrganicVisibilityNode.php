<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushTrackingOrganicVisibilityNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_tracking_organic_visibility';
    }

    public function name(): string
    {
        return 'Semrush: Tracking Organic Visibility';
    }

    public function description(): string
    {
        return 'The visibility index of a campaign in organic search, by date.';
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
                'linktype_filter' => Field::advanced(Field::text('Link type filter', 'Semrush\'s code: 0 includes local pack and hotels, 2 excludes local pack.', '0')),
                'business_name' => Field::advanced(Field::text('Business name', 'As in Google Business Profile.', null)),
                'serp_feature_filter' => Field::advanced(Field::text('SERP feature filter', null, null)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = ['action' => 'report', 'type' => 'tracking_visibility_organic'];

        foreach (['url', 'date_begin', 'date_end', 'display_tags', 'display_tags_condition', 'display_filter', 'linktype_filter', 'business_name', 'serp_feature_filter'] as $parameter) {
            $query[$parameter] = $config[$parameter] ?? null;
        }

        $answer = $this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->campaignId($config).'/tracking/', $query);
        $rows = array_values(array_filter((array) ($answer['data'] ?? []), 'is_array'));

        return [...array_diff_key($answer, ['data' => true]), 'rows' => $rows, 'count' => count($rows)];
    }
}
