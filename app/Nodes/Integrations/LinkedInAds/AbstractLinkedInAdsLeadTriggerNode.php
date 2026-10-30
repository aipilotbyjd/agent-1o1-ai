<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * New lead form responses of one kind, as a reader and as a trigger. LinkedIn can push
 * these, but only to one webhook of the whole app that it has validated with the app's
 * secret, so this polls by submission time. A new lead's run is told the question names
 * as well, since a response on its own only holds question numbers.
 */
abstract class AbstractLinkedInAdsLeadTriggerNode extends AbstractLinkedInAdsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * `SPONSORED`, `COMPANY` or `EVENT`.
     */
    abstract protected function leadType(): string;

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
                ...$this->formOwnerFields(),
                'form_id' => Field::advanced(Field::dynamic('Only this form', 'linkedin_ads.lead_forms', null, null, ['account_id'])),
                'form_version' => Field::advanced(Field::integer('Form version', 'With a form: the version of it to read. LinkedIn counts from 1.', 1, 1)),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only leads this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->getTimestampMs();
        $leads = array_map($this->shapeLead(...), $this->readLeads($run, $config, $this->leadType(), $since));

        return ['leads' => $leads, 'count' => count($leads)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'leads';
    }

    /**
     * The lead with its answers named after the form's questions. A form that can't be
     * read leaves the lead as it was, so no lead is lost to it.
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array
    {
        if (preg_match('/leadGenForm:(\d+)/', (string) ($item['form'] ?? ''), $match) !== 1) {
            return $item;
        }

        try {
            $questions = (array) data_get($this->linkedin($run, $config, 'GET', "/leadForms/{$match[1]}"), 'content.questions', []);
        } catch (Throwable) {
            return $item;
        }

        $names = [];

        foreach ($questions as $question) {
            if (is_array($question) && isset($question['questionId'])) {
                $names[$question['questionId']] = $question['name'] ?? null;
            }
        }

        $fields = [];

        foreach ((array) ($item['answers'] ?? []) as $answer) {
            $name = $names[$answer['question_id'] ?? ''] ?? null;

            if ($name !== null) {
                $fields[$name] = $answer['text'] ?? $answer['options'] ?? null;
            }
        }

        return [...$item, 'fields' => $fields];
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 100];
    }
}
