<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateDashboardNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_dashboard';
    }

    public function name(): string
    {
        return 'Datadog: Create Dashboard';
    }

    public function description(): string
    {
        return 'Creates a dashboard from a layout and widgets.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title'),
                'description' => Field::textarea('Description'),
                'layout_type' => Field::select('Layout', ['ordered' => 'Ordered', 'free' => 'Free'], null, 'ordered'),
                'widgets' => Field::json('Widgets', 'The widget definitions, as in the Datadog API.', 'array'),
                'tags' => Field::advanced(Field::list('Tags', 'Team tags, like team:payments.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter([
            'title' => $this->requiredString($config, 'title', 'The title'),
            'layout_type' => $this->text($config, 'layout_type') ?? 'ordered',
            'description' => $this->text($config, 'description'),
            'widgets' => $this->jsonOf($config, 'widgets', 'Widgets') ?? [],
            'tags' => $this->listOf($config, 'tags') ?: null,
        ], fn (mixed $value): bool => $value !== null);

        return $this->datadog($run, $config, 'POST', '/api/v1/dashboard', [], $body);
    }
}
