<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateDashboardNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_dashboard';
    }

    public function name(): string
    {
        return 'Datadog: Update Dashboard';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on a dashboard; the rest stays as it is.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['dashboard_id'],
            'properties' => [
                ...$this->credentialFields(),
                'dashboard_id' => $this->dashboardField(),
                'title' => Field::text('Title'),
                'description' => Field::textarea('Description'),
                'widgets' => Field::json('Widgets', 'Replaces every widget.', 'array'),
                'layout_type' => Field::advanced(Field::select('Layout', ['ordered' => 'Ordered', 'free' => 'Free'])),
                'tags' => Field::advanced(Field::list('Tags')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'dashboard_id', 'The dashboard');
        $current = $this->datadog($run, $config, 'GET', "/api/v1/dashboard/{$id}");
        $body = array_intersect_key($current, array_flip(['title', 'description', 'layout_type', 'widgets', 'tags', 'template_variables', 'notify_list', 'reflow_type']));
        $body = [...$body, ...array_filter(['title' => $this->text($config, 'title'), 'description' => $this->text($config, 'description'), 'layout_type' => $this->text($config, 'layout_type'), 'widgets' => $this->jsonOf($config, 'widgets', 'Widgets'), 'tags' => $this->listOf($config, 'tags') ?: null], fn (mixed $value): bool => $value !== null)];

        return $this->datadog($run, $config, 'PUT', "/api/v1/dashboard/{$id}", [], $body);
    }
}
