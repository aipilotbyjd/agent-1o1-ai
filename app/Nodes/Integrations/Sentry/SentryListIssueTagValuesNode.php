<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryListIssueTagValuesNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_issue_tag_values';
    }

    public function name(): string
    {
        return 'Sentry: List Issue Tag Values';
    }

    public function description(): string
    {
        return 'Lists the values of a tag on an issue.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue_id', 'key'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'issue_id' => Field::text('Issue', 'Its numeric id or short id, like BACKEND-1A.', 'BACKEND-1A'),
                'key' => Field::text('Tag', null, 'browser'),
                'environment' => Field::text('Environment'),
                'sort' => Field::advanced(Field::select('Order', ['age' => 'Age', 'count' => 'Count', 'date' => 'Date', 'id' => 'Id'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/issues/'.$this->issueId($config).'/tags/'.rawurlencode($this->tagKey($config)).'/values/', 'values', ['environment' => $this->text($config, 'environment'), 'sort' => $this->text($config, 'sort')]);
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
