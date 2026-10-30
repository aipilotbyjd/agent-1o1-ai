<?php

namespace App\Nodes\Integrations\Concerns;

use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Reading a step's settings for the web-data apps (Firecrawl, Apify, Exa,
 * Perplexity, Tavily): required text, ids that go in a URL, links, lists and JSON
 * that may arrive as text from a template, and the "any API call" node they share.
 */
trait ReadsApiSettings
{
    /**
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
     * An id placed in a URL: checked, so it can never add to or leave the path.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function pathId(array $config, string $key, string $label): string
    {
        $value = $this->requiredString($config, $key, $label);

        return preg_match('/^[A-Za-z0-9._~-]{1,200}$/', $value) === 1 && ! str_contains($value, '..')
            ? $value
            : throw new InvalidArgumentException("{$label} may only hold letters, digits, dots, ~, _ and -.");
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 200, string $key = 'limit'): int
    {
        return max(1, min($max, (int) ($config[$key] ?? $default)));
    }

    /**
     * A whole number setting; null when it is left empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function intOf(array $config, string $key): ?int
    {
        return is_numeric($config[$key] ?? null) ? (int) $config[$key] : null;
    }

    /**
     * A number setting; null when it is left empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function floatOf(array $config, string $key): ?float
    {
        return is_numeric($config[$key] ?? null) ? (float) $config[$key] : null;
    }

    /**
     * A switch; null when it is not set, so the provider's own default applies.
     *
     * @param  array<string, mixed>  $config
     */
    protected function boolOf(array $config, string $key): ?bool
    {
        return isset($config[$key]) && $config[$key] !== '' ? filter_var($config[$key], FILTER_VALIDATE_BOOLEAN) : null;
    }

    /**
     * A text setting; null when it is left empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function textOf(array $config, string $key): ?string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * A list that may arrive as an array, or as text split by commas or new lines.
     *
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        $parts = is_array($value) ? $value : preg_split('/[\r\n,]+/', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $part): string => is_scalar($part) ? trim((string) $part) : '', (array) $parts), fn (string $part): bool => $part !== ''));
    }

    /**
     * A checked web link.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function webUrl(array $config, string $key = 'url', string $label = 'The URL'): string
    {
        return $this->checkedUrl($this->requiredString($config, $key, $label));
    }

    /**
     * Checked web links from a list or text.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function webUrls(array $config, string $key = 'urls', string $label = 'At least one URL'): array
    {
        $urls = $this->stringList($config[$key] ?? []);

        return $urls !== [] ? array_map($this->checkedUrl(...), $urls) : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function checkedUrl(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false
            ? $url
            : throw new InvalidArgumentException("\"{$url}\" is not a web address (http or https).");
    }

    /**
     * A JSON setting that may arrive as text from a template.
     *
     * @return array<int|string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    protected function decoded(mixed $value, string $label): ?array
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                return null;
            }

            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : throw new InvalidArgumentException("{$label} must be valid JSON.");
        }

        return is_array($value) && $value !== [] ? $value : null;
    }

    /**
     * The body of a request, without the settings that were left empty. `false`
     * and `0` stay: they are answers, not gaps.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function withoutEmpty(array $body): array
    {
        return array_filter($body, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * The path of an "any API call" node. The host is always the app's own: the
     * path can't leave it, go up a level or carry its own query.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function apiPath(array $config): string
    {
        $path = $this->requiredString($config, 'path', 'The path');

        if (preg_match('~^/[A-Za-z0-9._\~/-]*$~', $path) !== 1 || str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException('The path must start with / and hold only letters, digits, /, ., ~, _ and - (put parameters in Query).');
        }

        return $path;
    }

    /**
     * The HTTP method of an "any API call" node.
     *
     * @param  array<string, mixed>  $config
     */
    protected function apiMethod(array $config): string
    {
        $method = strtoupper(trim((string) ($config['method'] ?? 'GET')));

        return in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true) ? $method : throw new InvalidArgumentException('The method must be GET, POST, PUT, PATCH or DELETE.');
    }

    /**
     * The fields of an "any API call" node.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function apiRequestFields(string $placeholder): array
    {
        return [
            'method' => Field::select('Method', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], null, 'GET'),
            'path' => Field::text('Path', 'The address after the API root, with ids filled in.', $placeholder),
            'query' => Field::keyValue('Query'),
            'body' => Field::json('Body', 'The JSON to send, for POST, PUT and PATCH.'),
        ];
    }
}
