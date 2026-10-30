<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushCreateTrackingCampaignNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_create_tracking_campaign';
    }

    public function name(): string
    {
        return 'Semrush: Enable Position Tracking';
    }

    public function description(): string
    {
        return 'Creates a Position Tracking campaign in a project. A new campaign has no keywords yet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'tracking_url', 'tracking_url_type', 'location_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'tracking_url' => Field::text('Tracked URL', null, 'example.com'),
                'tracking_url_type' => Field::select('URL type', ['rootdomain' => 'Root domain', 'subdomain' => 'Subdomain', 'subfolder' => 'Subfolder', 'url' => 'URL'], null, 'rootdomain'),
                'location_id' => Field::integer('Location id', 'From Search Tracking Locations. 2840 is the United States.', null, 1),
                'device' => Field::select('Device', ['desktop', 'phone', 'tablet'], null, 'desktop'),
                'weekly_notification' => Field::advanced(Field::boolean('Weekly emails', null, true)),
                'timezone' => Field::advanced(Field::integer('Time zone', 'The offset from UTC, in hours.')),
                'business_name' => Field::advanced(Field::text('Business name')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter([
            'tracking_url' => $this->requiredString($config, 'tracking_url', 'The tracked URL'),
            'tracking_url_type' => (string) ($config['tracking_url_type'] ?? 'rootdomain'),
            'location_id' => (int) $this->requiredString($config, 'location_id', 'The location'),
            'device' => $config['device'] ?? null,
            'weekly_notification' => $config['weekly_notification'] ?? null,
            'timezone' => is_numeric($config['timezone'] ?? null) ? (int) $config['timezone'] : null,
            'business_name' => $config['business_name'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
        $created = $this->semrushJson($run, $config, 'POST', '/management/v1/projects/'.$this->projectId($config).'/tracking/enable', [], $body);

        return ['status' => $created['status'] ?? null, 'campaign_id' => data_get($created, 'result.campaign_id')];
    }
}
