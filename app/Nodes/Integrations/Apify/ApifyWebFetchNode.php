<?php

namespace App\Nodes\Integrations\Apify;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Facades\Http;

class ApifyWebFetchNode extends AbstractApifyNode
{
    public function type(): string
    {
        return 'apify_web_fetch';
    }

    public function name(): string
    {
        return 'Apify: Web Fetch';
    }

    public function description(): string
    {
        return 'Reads one web page, PDF or file as markdown, text, HTML or links, through Apify\'s Web Fetch Actor. Costs a fetch event.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('URL', null, 'https://example.com'),
                'formats' => Field::list('Formats', 'markdown, html, text, links, raw. Leave empty to choose by the file type.', 'markdown'),
                'wait' => Field::advanced(Field::integer('Wait first (ms)', 'Opens the page in a real browser and waits.', null, 1, 60000)),
                'request_headers' => Field::advanced(Field::keyValue('Request headers')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(75)->post('https://web-fetch.apify.actor/', $this->withoutEmpty([
            'url' => $this->webUrl($config),
            'formats' => $this->stringList($config['formats'] ?? []),
            'wait' => $this->intOf($config, 'wait'),
            'headers' => $this->decoded($config['request_headers'] ?? null, 'The request headers'),
        ]));
        $this->assertOk($response);

        return (array) $response->json();
    }
}
