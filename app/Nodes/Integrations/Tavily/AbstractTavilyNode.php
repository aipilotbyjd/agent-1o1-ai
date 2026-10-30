<?php

namespace App\Nodes\Integrations\Tavily;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ReadsApiSettings;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request, error handling and validation for the Tavily node family (the key
 * as a bearer token). Crawl and map answer in one request and take a `timeout` in
 * seconds, kept below `MAX_TIMEOUT_SECONDS` so the step can't hold a worker for
 * minutes. Research takes longer, so it is a start and a status call.
 */
abstract class AbstractTavilyNode implements DeclaresEffect, NodeContract
{
    use ReadsApiSettings;
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.tavily.com';

    protected const int MAX_TIMEOUT_SECONDS = 90;

    public function category(): string
    {
        return 'tavily';
    }

    /**
     * Call Tavily and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException
     */
    protected function tavily(Run $run, array $config, string $method, string $path, ?array $body = null, int $timeout = 30, array $query = []): array
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout($timeout)->send(strtoupper($method), self::API_URL.$path, $options);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = $response->json('detail');
        $message = (string) (is_array($detail) ? ($detail['error'] ?? json_encode($detail)) : ($detail ?? $response->json('error') ?? "HTTP {$response->status()}"));
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Tavily: the API key was refused.',
            in_array($response->status(), [402, 432, 433], true) => ' The Tavily plan or pay-as-you-go limit is used up.',
            $response->status() === 403 => ' The Tavily key is not allowed to do that.',
            $response->status() === 429 => ' Tavily is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Tavily error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The settings a crawl and a map share.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function walkFields(): array
    {
        return [
            'url' => Field::text('Start at', null, 'https://docs.example.com'),
            'instructions' => Field::textarea('Look for', 'In words: Tavily follows the pages that fit (costs more credits).'),
            'max_depth' => Field::integer('How deep', 'Links to follow from the start.', 1, 1, 5),
            'max_breadth' => Field::advanced(Field::integer('Links per page', null, 20, 1)),
            'limit' => Field::integer('Max pages', null, 50, 1),
            'select_paths' => Field::advanced(Field::list('Only paths like', 'Patterns.', '/docs/.*')),
            'select_domains' => Field::advanced(Field::list('Only sites like')),
            'exclude_paths' => Field::advanced(Field::list('Not paths like')),
            'exclude_domains' => Field::advanced(Field::list('Not sites like')),
            'allow_external' => Field::advanced(Field::boolean('Include links to other sites', null, true)),
            'timeout' => Field::advanced(Field::integer('Timeout (seconds)', null, 60, 10, self::MAX_TIMEOUT_SECONDS)),
        ];
    }

    /**
     * The settings a crawl and a map share, as Tavily names them.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function walkBody(array $config): array
    {
        return $this->withoutEmpty([
            'url' => $this->webUrl($config),
            'instructions' => $this->textOf($config, 'instructions'),
            'max_depth' => $this->intOf($config, 'max_depth'),
            'max_breadth' => $this->intOf($config, 'max_breadth'),
            'limit' => $this->intOf($config, 'limit'),
            'select_paths' => $this->stringList($config['select_paths'] ?? []),
            'select_domains' => $this->stringList($config['select_domains'] ?? []),
            'exclude_paths' => $this->stringList($config['exclude_paths'] ?? []),
            'exclude_domains' => $this->stringList($config['exclude_domains'] ?? []),
            'allow_external' => $this->boolOf($config, 'allow_external'),
            'timeout' => $this->timeoutSeconds($config),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function timeoutSeconds(array $config): int
    {
        return max(10, min(self::MAX_TIMEOUT_SECONDS, (int) ($config['timeout'] ?? 60)));
    }
}
