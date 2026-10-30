<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class AhrefsListRankChangesNode extends AbstractAhrefsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'ahrefs_list_rank_changes';
    }

    public function name(): string
    {
        return 'Ahrefs: List Rank Changes';
    }

    public function description(): string
    {
        return 'The tracked keywords of a project whose position has changed since the comparison date. As a trigger it fires when a position changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'device'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'device' => Field::select('Device', ['desktop', 'mobile'], null, 'desktop'),
                'days' => Field::integer('Compare with', 'Days back.', 1, 1, 90),
                'limit' => Field::integer('Limit', 'The most rows to read each time.', 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $days = max(1, (int) ($config['days'] ?? 1));
        $answer = $this->ahrefs($run, $config, 'GET', '/rank-tracker/overview', [
            'select' => 'keyword,position,position_prev,position_diff,url,volume,location,country,language,tags,serp_updated',
            'project_id' => $this->requiredString($config, 'project_id', 'The project'),
            'device' => $this->requiredString($config, 'device', 'The device'),
            'date' => now()->utc()->toDateString(),
            'date_compared' => now()->utc()->subDays($days)->toDateString(),
            'limit' => $config['limit'] ?? 100,
        ]);
        $changed = array_values(array_filter((array) ($answer['overviews'] ?? []), fn (mixed $keyword): bool => is_array($keyword) && ($keyword['position'] ?? null) !== ($keyword['position_prev'] ?? null)));

        return ['keywords' => $changed, 'count' => count($changed)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'keywords';
    }

    /**
     * A change is known by the keyword, its place and its new position.
     */
    public function triggerItemId(array $item): string
    {
        return implode('|', [$item['keyword'] ?? '', $item['location'] ?? '', $item['country'] ?? '', $item['position'] ?? 'none', $item['serp_updated'] ?? '']);
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Ahrefs: Keyword rank changed';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a tracked keyword moves up or down. Checked daily, as each check costs API units.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1440;
    }
}
