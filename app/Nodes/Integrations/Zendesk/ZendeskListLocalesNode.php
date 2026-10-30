<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

class ZendeskListLocalesNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_locales';
    }

    public function name(): string
    {
        return 'Zendesk: List Locales';
    }

    public function description(): string
    {
        return 'Lists the languages the account supports. As a trigger it starts the flow when one changes.';
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
        $locales = (array) ($this->zendesk($run, $config, 'GET', '/locales')['locales'] ?? []);

        return ['locales' => $locales, 'count' => count($locales)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'locales';
    }

    public function triggerItemId(array $item): string
    {
        return ($item['id'] ?? '').':'.($item['updated_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Zendesk: Locale updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a language setting is added or changed. Checked every few minutes.';
    }
}
