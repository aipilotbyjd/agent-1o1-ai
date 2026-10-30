<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ReadsApiSettings;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the Perplexity
 * node family (the key as a bearer token). The Agent API is Perplexity's main way to
 * get an answer: a step waits for one up to `ANSWER_TIMEOUT_SECONDS`. Deep research
 * takes minutes, so it runs in the background: a Start node returns an id, and
 * Get Agent Response reads it.
 */
abstract class AbstractPerplexityNode implements DeclaresEffect, NodeContract
{
    use ReadsApiSettings;
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.perplexity.ai';

    protected const int ANSWER_TIMEOUT_SECONDS = 90;

    public function category(): string
    {
        return 'perplexity';
    }

    /**
     * Call Perplexity and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query  null values are dropped
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException
     */
    protected function perplexity(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = [], int $timeout = 30): array
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

        $message = (string) ($response->json('error.message') ?? $response->json('message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Perplexity: the API key was refused.',
            $response->status() === 402 => ' The Perplexity account is out of credit.',
            $response->status() === 403 => ' The Perplexity key is not allowed to do that.',
            $response->status() === 429 => ' Perplexity is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Perplexity error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The messages of a chat: a list given as JSON, or a question and system text.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function messages(array $config): array
    {
        $messages = $this->decoded($config['messages'] ?? null, 'The messages');

        if ($messages !== null) {
            return array_values($messages);
        }

        $system = $this->textOf($config, 'system');

        return [
            ...($system === null ? [] : [['role' => 'system', 'content' => $system]]),
            ['role' => 'user', 'content' => $this->requiredString($config, 'prompt', 'The question')],
        ];
    }

    /**
     * The settings of an Agent API request.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function agentFields(bool $withEffort = true): array
    {
        return [
            'prompt' => Field::textarea('Question'),
            ...($withEffort ? ['effort' => Field::select('Effort', ['fast', 'low', 'medium', 'high', 'xhigh'], 'How much work to put in. Higher takes longer: use Start Agent Response above low.', 'low')] : []),
            'model' => Field::advanced(Field::dynamic('Model', 'perplexity.models', 'Overrides the model the effort level picks.', 'openai/gpt-6.1-sol')),
            'system' => Field::advanced(Field::textarea('Instructions')),
            'max_output_tokens' => Field::advanced(Field::integer('Max answer length (tokens)', 'Required for anthropic/ models.', null, 1)),
            'max_steps' => Field::advanced(Field::integer('Max steps', null, null, 1)),
            'temperature' => Field::advanced(Field::integer('Temperature', null, null, 0, 2)),
            'language' => Field::advanced(Field::text('Answer language', 'A language code.', 'en')),
            'previous_response_id' => Field::advanced(Field::text('Continue from', 'The id of an earlier response.')),
            'response_schema' => Field::advanced(Field::json('Answer shape', 'A JSON schema the answer must follow.')),
            'tools' => Field::advanced(Field::json('Tools', 'Such as [{"type": "web_search", "filters": {"search_domain_filter": ["example.com"]}}].', 'array')),
        ];
    }

    /**
     * An Agent API request.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function agentBody(array $config, ?string $effort = null, bool $background = false): array
    {
        $schema = $this->decoded($config['response_schema'] ?? null, 'The answer shape');

        return $this->withoutEmpty([
            'input' => $this->requiredString($config, 'prompt', 'The question'),
            'effort' => $effort ?? $this->textOf($config, 'effort'),
            'model' => $this->textOf($config, 'model'),
            'instructions' => $this->textOf($config, 'system'),
            'max_output_tokens' => $this->intOf($config, 'max_output_tokens'),
            'max_steps' => $this->intOf($config, 'max_steps'),
            'temperature' => $this->floatOf($config, 'temperature'),
            'language_preference' => $this->textOf($config, 'language'),
            'previous_response_id' => $this->textOf($config, 'previous_response_id'),
            'response_format' => $schema === null ? null : ['type' => 'json_schema', 'json_schema' => ['name' => 'answer', 'schema' => $schema]],
            'tools' => $this->decoded($config['tools'] ?? null, 'The tools'),
            'background' => $background ? true : null,
        ]);
    }

    /**
     * An Agent API response: its answer, sources and how it went.
     *
     * @param  array<int|string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function shapeAgentResponse(array $response): array
    {
        $text = [];
        $citations = [];
        $sources = [];

        foreach ((array) ($response['output'] ?? []) as $item) {
            if (($item['type'] ?? null) === 'message') {
                foreach ((array) ($item['content'] ?? []) as $part) {
                    $text[] = (string) ($part['text'] ?? '');
                    array_push($citations, ...array_values(array_filter(array_column((array) ($part['annotations'] ?? []), 'url'))));
                }
            }

            if (($item['type'] ?? null) === 'search_results') {
                array_push($sources, ...array_map(fn (array $result): array => ['title' => $result['title'] ?? null, 'url' => $result['url'] ?? null, 'snippet' => $result['snippet'] ?? null, 'date' => $result['date'] ?? null], array_values(array_filter((array) ($item['results'] ?? []), 'is_array'))));
            }
        }

        return [
            'id' => $response['id'] ?? null,
            'status' => $response['status'] ?? null,
            'finished' => in_array($response['status'] ?? null, ['completed', 'failed', 'incomplete', 'cancelled'], true),
            'answer' => implode('', $text),
            'citations' => array_values(array_unique($citations)),
            'sources' => $sources,
            'model' => $response['model'] ?? null,
            'error' => data_get($response, 'error.message'),
            'usage' => $response['usage'] ?? null,
        ];
    }

    /**
     * An OpenAI-style chat answer.
     *
     * @param  array<int|string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function shapeChat(array $response): array
    {
        return $this->withoutEmpty([
            'id' => $response['id'] ?? null,
            'answer' => data_get($response, 'choices.0.message.content'),
            'citations' => $response['citations'] ?? null,
            'sources' => $response['search_results'] ?? null,
            'finish_reason' => data_get($response, 'choices.0.finish_reason'),
            'model' => $response['model'] ?? null,
            'usage' => $response['usage'] ?? null,
        ]);
    }

    /**
     * The embeddings in an answer.
     *
     * @param  array<int|string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function shapeEmbeddings(array $response): array
    {
        $data = (array) ($response['data'] ?? []);

        return ['embeddings' => array_column($data, 'embedding'), 'count' => count($data), 'data' => $data, 'model' => $response['model'] ?? null, 'usage' => $response['usage'] ?? null];
    }
}
