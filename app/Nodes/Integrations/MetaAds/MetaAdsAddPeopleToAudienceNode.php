<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsAddPeopleToAudienceNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_add_people_to_audience';
    }

    public function name(): string
    {
        return 'Meta Ads: Add People to Audience by Several Keys';
    }

    public function description(): string
    {
        return 'Adds people to a custom audience matched on several details at once, such as name, phone and country. Each value is normalized and hashed as Meta requires.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id', 'schema', 'rows'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'audience_id' => Field::dynamic('Audience', 'meta_ads.audiences', null, null, ['account_id']),
                'schema' => Field::list('Columns', 'In order, from EMAIL, PHONE, FN, LN, FI, GEN, DOBY, DOBM, DOBD, CT, ST, ZIP, COUNTRY, MADID and EXTERN_ID.'),
                'rows' => Field::json('People', 'A list of up to 10,000 rows, each with one value per column.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $schema = array_map('strtoupper', $this->stringList($config, 'schema'));
        $rows = $this->jsonSetting($config, 'rows', 'The people', true);

        if ($schema === [] || count($rows) > 10000) {
            throw new InvalidArgumentException('Give the columns and at most 10,000 people.');
        }

        $payload = ['schema' => $schema, 'data' => $this->hashAudienceRows($schema, array_map(fn (mixed $row): array => array_values((array) $row), array_values($rows)))];
        $body = $this->graph($run, $config, 'POST', '/'.$this->nodeId($config, 'audience_id', 'The audience').'/users', ['payload' => $payload, ...$this->audienceSession('POST', count($rows))]);

        return ['added' => count($rows), 'num_received' => $body['num_received'] ?? null, 'num_invalid_entries' => $body['num_invalid_entries'] ?? null, 'session_id' => $body['session_id'] ?? null];
    }
}
