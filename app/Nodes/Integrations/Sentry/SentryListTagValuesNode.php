<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryListTagValuesNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_tag_values';
    }

    public function name(): string
    {
        return 'Sentry: List Tag Values';
    }

    public function description(): string
    {
        return 'Lists the values of a tag in a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'key'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'key' => Field::text('Tag', null, 'browser'),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/tags/'.rawurlencode($this->tagKey($config)).'/values/', 'values');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function tagKey(array $config): string
    {
        $key = $this->requiredString($config, 'key', 'The tag');

        return preg_match('/^[A-Za-z0-9._:-]{1,200}$/', $key) === 1 ? $key : throw new InvalidArgumentException('That is not a tag name.');
    }
}
