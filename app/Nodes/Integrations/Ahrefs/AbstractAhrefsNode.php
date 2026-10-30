<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling and result shaping for the Ahrefs node family: API
 * v3 with a bearer key. Every id travels as a query parameter or in the body, so
 * nothing a member types ever becomes part of a path. A report answers one key holding
 * its rows (`{"backlinks": [...]}`) or a single object (`{"metrics": {...}}`); the
 * rows come back with a count and the object comes back as the output.
 */
abstract class AbstractAhrefsNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string BASE_URL = 'https://api.ahrefs.com/v3';

    public function category(): string
    {
        return 'ahrefs';
    }

    /**
     * A call to Ahrefs, decoded. An empty answer comes back as `ok`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null and empty values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function ahrefs(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $options = ['query' => ['output' => 'json', ...array_filter($query, fn (mixed $value): bool => $value !== null && $value !== '')]];

        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(60)->send(strtoupper($method), self::BASE_URL.$path, $options);

        $this->assertOk($response);

        return (array) ($response->json() ?? ['ok' => true]);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $error = $response->json('error');
        $message = (string) ((is_string($error) ? $error : null) ?? $response->json('error.message') ?? $response->json('message') ?? "HTTP {$response->status()}");
        $hint = match ($response->status()) {
            401 => ' Reconnect Ahrefs: the API key was refused.',
            403 => ' The Ahrefs plan or key does not allow this.',
            402 => ' The Ahrefs workspace has run out of API units.',
            429 => ' Ahrefs is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Ahrefs error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * A report's answer: its rows with a count under the key, or its one object.
     *
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    protected function shape(array $answer, string $key): array
    {
        $value = $answer[$key] ?? null;
        $name = str_replace('-', '_', $key);

        if (is_array($value) && array_is_list($value)) {
            return [$name => $value, 'count' => count($value)];
        }

        return is_array($value) ? $value : $answer;
    }

    /**
     * A required text setting, trimmed.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredString(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("{$label} is required.");
    }
}
