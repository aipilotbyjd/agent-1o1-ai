<?php

namespace App\Nodes\Integrations\Semrush;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the Semrush
 * node family. Semrush takes the API key in the URL, so a dropped connection is
 * reported without the address. The SEO and Trends reports answer semicolon-separated
 * text with a header line, which comes back as rows keyed by the snake_case header;
 * Projects, Position Tracking and Site Audit answer JSON. Errors can arrive with a
 * 200 status as `ERROR 50 :: NOTHING FOUND`.
 */
abstract class AbstractSemrushNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string BASE_URL = 'https://api.semrush.com';

    public function category(): string
    {
        return 'semrush';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null and empty values are dropped
     * @param  array<int|string, mixed>|null  $body
     *
     * @throws RuntimeException
     */
    protected function semrushResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $query = array_filter(['key' => $this->resolveAccessToken($run, $config), ...$query], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        $queryString = preg_replace('/%5B\d+%5D=/', '[]=', http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        try {
            return Http::acceptJson()->timeout(60)->send(strtoupper($method), self::BASE_URL.$path.'?'.$queryString, $body !== null ? ['json' => $body] : []);
        } catch (ConnectionException) {
            throw new RuntimeException("Couldn't reach Semrush. Try again in a moment.");
        }
    }

    /**
     * A call that answers JSON.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<int|string, mixed>|null  $body
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException
     */
    protected function semrushJson(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->semrushResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? ['ok' => true]);
    }

    /**
     * A report that answers semicolon-separated text, as rows.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return array{rows: list<array<string, string>>, count: int}
     *
     * @throws RuntimeException
     */
    protected function semrushRows(Run $run, array $config, string $path, array $query): array
    {
        $response = $this->semrushResponse($run, $config, 'GET', $path, $query);

        $this->assertOk($response);

        $rows = self::parseRows($response->body());

        return ['rows' => $rows, 'count' => count($rows)];
    }

    /**
     * Semicolon-separated text with a header line, as rows keyed by the header.
     *
     * @return list<array<string, string>>
     */
    public static function parseRows(string $body): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', trim($body)) ?: [], fn (string $line): bool => trim($line) !== ''));

        if ($lines === [] || self::isError($lines[0])) {
            return [];
        }

        $header = array_map(self::columnKey(...), str_getcsv(array_shift($lines), ';', '"', ''));
        $width = count($header);

        return array_map(function (string $line) use ($header, $width): array {
            $cells = array_slice(array_pad(str_getcsv($line, ';', '"', ''), $width, ''), 0, $width);

            return array_combine($header, array_map(fn (?string $cell): string => (string) $cell, $cells));
        }, $lines);
    }

    /**
     * `Traffic (%)` to `traffic`, `Search Volume` to `search_volume`.
     */
    public static function columnKey(?string $column): string
    {
        $key = preg_replace('/\s*\(.*?\)\s*/', '', (string) $column) ?: (string) $column;
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($key)), '_');

        return $key !== '' ? $key : 'column';
    }

    /**
     * Whether a line is Semrush's error form.
     */
    public static function isError(string $line): bool
    {
        return preg_match('/^\s*ERROR\s+\d+/i', $line) === 1;
    }

    /**
     * A report with nothing in it (`ERROR 50`) is an empty answer, not a failure.
     *
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        $body = trim($response->body());

        if (preg_match('/^ERROR\s+(\d+)\s*::\s*(.*)$/is', $body, $error) === 1) {
            if ((int) $error[1] === 50) {
                return;
            }

            $hint = match (true) {
                in_array((int) $error[1], [120, 121, 122, 123], true) => ' Reconnect Semrush: the API key was refused.',
                in_array((int) $error[1], [130, 131], true) => ' API access is not part of this Semrush plan.',
                (int) $error[1] === 132 => ' The Semrush account has run out of API units.',
                (int) $error[1] === 134 => ' The Semrush account has reached its API limit.',
                default => '',
            };

            throw new RuntimeException('Semrush error '.$error[1].': '.mb_substr($error[2], 0, 300).'.'.$hint);
        }

        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? $response->json('error.message') ?? (is_string($response->json('error')) ? $response->json('error') : null) ?? "HTTP {$response->status()}");
        $hint = match ($response->status()) {
            401 => ' Reconnect Semrush: the API key was refused.',
            403 => ' The Semrush plan does not include this, or the account has run out of API units.',
            429 => ' Semrush is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Semrush error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
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

    /**
     * A domain without its scheme and trailing slash, as the reports want it.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function domain(array $config, string $key = 'domain', string $label = 'The domain'): string
    {
        return self::bareDomain($this->requiredString($config, $key, $label));
    }

    /**
     * `https://www.example.com/` to `www.example.com`.
     */
    public static function bareDomain(string $value): string
    {
        return rtrim((string) preg_replace('~^[a-z][a-z0-9+.-]*://~i', '', trim($value)), '/');
    }

    /**
     * A project id, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function projectId(array $config): string
    {
        $value = $this->requiredString($config, 'project_id', 'The project');

        return ctype_digit($value) ? $value : throw new InvalidArgumentException('The project id must be a number.');
    }

    /**
     * A Position Tracking campaign id such as `643526670283248_17238`, checked so it
     * can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function campaignId(array $config): string
    {
        $value = $this->requiredString($config, 'campaign_id', 'The campaign');

        return preg_match('/^\d+_\d+$/', $value) === 1 ? $value : throw new InvalidArgumentException('The campaign id looks like 643526670283248_17238.');
    }

    /**
     * A snapshot or page id, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function hexId(array $config, string $key, string $label): string
    {
        $value = $this->requiredString($config, $key, $label);

        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} may only hold letters, digits, _ and -.");
    }

    /**
     * A list setting: a list, or text separated by commas or new lines.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function listOf(array $config, string $key): array
    {
        $value = $config[$key] ?? [];
        $items = is_string($value) ? (preg_split('/[,\n]/', $value) ?: []) : (array) $value;

        return array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $items), fn (string $item): bool => $item !== ''));
    }

    /**
     * A list setting that can't be empty.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function requiredList(array $config, string $key, string $label): array
    {
        $items = $this->listOf($config, $key);

        return $items !== [] ? $items : throw new InvalidArgumentException("{$label} needs at least one value.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectField(): array
    {
        return Field::dynamic('Project', 'semrush.projects', 'Pick a project, or enter its id. Listing projects costs 100 API units.', '643526670283248');
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignField(): array
    {
        return Field::dynamic('Campaign', 'semrush.campaigns', 'Pick a Position Tracking campaign, or enter its id. Listing campaigns costs 100 API units.', '643526670283248_17238', dependsOn: ['project_id']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotField(): array
    {
        return Field::dynamic('Audit', 'semrush.snapshots', 'Pick a finished audit, or enter its snapshot id.', '54102d92e4b0f889a040c9c8', dependsOn: ['project_id']);
    }
}
