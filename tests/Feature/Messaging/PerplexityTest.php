<?php

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Models\User;
use App\Nodes\Integrations\Perplexity\AbstractPerplexityNode;
use App\Nodes\Integrations\Perplexity\PerplexityAskNode;
use App\Nodes\Integrations\Perplexity\PerplexityCancelAgentResponseNode;
use App\Nodes\Integrations\Perplexity\PerplexityChatCompletionNode;
use App\Nodes\Integrations\Perplexity\PerplexityCreateAgentResponseNode;
use App\Nodes\Integrations\Perplexity\PerplexityCreateContextualizedEmbeddingsNode;
use App\Nodes\Integrations\Perplexity\PerplexityCreateEmbeddingsNode;
use App\Nodes\Integrations\Perplexity\PerplexityGetAgentResponseNode;
use App\Nodes\Integrations\Perplexity\PerplexityReasonNode;
use App\Nodes\Integrations\Perplexity\PerplexitySearchNode;
use App\Nodes\Integrations\Perplexity\PerplexityStartResearchNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\PerplexityOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/WebDataTestHelpers.php';

/**
 * @param  array<string, mixed>  $routes
 * @param  array<int|string, mixed>|null  $fallback
 */
function fakePerplexity(array $routes, ?array $fallback = null): void
{
    webDataFake('*', '', $routes, $fallback);
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function perplexityConfig(array $config = []): array
{
    return ['access_token' => 'pplx-key', ...$config];
}

/**
 * An Agent API answer, with a message and its sources.
 *
 * @return array<string, mixed>
 */
function perplexityAgentAnswer(string $status = 'completed'): array
{
    return ['id' => 'resp_1', 'object' => 'response', 'model' => 'openai/gpt', 'status' => $status, 'created_at' => 1, 'output' => [
        ['type' => 'search_results', 'results' => [['id' => 1, 'title' => 'A', 'url' => 'https://a.io', 'snippet' => 'Text']]],
        ['type' => 'message', 'id' => 'm', 'role' => 'assistant', 'status' => 'completed', 'content' => [['type' => 'output_text', 'text' => 'The answer', 'annotations' => [['url' => 'https://a.io', 'title' => 'A']]]]],
    ], 'usage' => ['total_tokens' => 9]];
}

describe('perplexity nodes', function () {
    it('registers the nodes under the perplexity connector, each with a sound schema', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'perplexity_'));

        expect(count($types))->toBeGreaterThanOrEqual(15);

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $schema = $node->configSchema();

            expect($node)->toBeInstanceOf(AbstractPerplexityNode::class)
                ->and($node->category())->toBe('perplexity')
                ->and($node->name())->toStartWith('Perplexity: ')
                ->and($node->effect([]))->toBeInstanceOf(ActionEffect::class)
                ->and(array_diff($schema['required'], array_keys($schema['properties'])))->toBe([]);
        }
    });

    it('runs every node with every setting filled, only ever calling Perplexity', function () {
        fakePerplexity([], ['id' => 'x', 'status' => 'completed', 'output' => [], 'data' => [], 'results' => [], 'choices' => []]);
        $registry = app(NodeRegistry::class);
        $run = Run::factory()->create();
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'perplexity_'));

        foreach ($types as $type) {
            $node = $registry->resolve($type);
            $config = webDataConfigFor($node->configSchema(), ['path' => '/v1/models', 'method' => 'POST', 'model' => 'sonar', 'input' => $type === 'perplexity_create_embeddings' ? ['hello'] : [['a', 'b']]]);

            expect($node->execute($run, $config, []))->toBeArray();
        }

        foreach (Http::recorded() as [$request]) {
            expect($request->url())->toStartWith('https://api.perplexity.ai/')->and($request->header('Authorization')[0])->toBe('Bearer the-key');
        }
    });

    it('searches with filters, and sends several searches as a list', function () {
        fakePerplexity(['POST /search' => ['id' => 's1', 'results' => [['title' => 'A', 'url' => 'https://a.io', 'snippet' => 'Text']]]]);
        $run = Run::factory()->create();

        $output = (new PerplexitySearchNode)->execute($run, perplexityConfig(['query' => 'ai', 'max_results' => 3, 'search_recency_filter' => 'week', 'search_domain_filter' => 'a.io, -b.io']), []);

        expect($output)->toMatchArray(['count' => 1, 'id' => 's1'])
            ->and(webDataBody('POST /search', ''))->toBe(['query' => 'ai', 'max_results' => 3, 'search_recency_filter' => 'week', 'search_domain_filter' => ['a.io', '-b.io']])
            ->and(webDataRequest('POST /search', '')->header('Authorization')[0])->toBe('Bearer pplx-key');

        (new PerplexitySearchNode)->execute($run, perplexityConfig(['query' => "one\ntwo"]), []);

        expect(webDataBody('POST /search', '')['query'])->toBe(['one', 'two'])
            ->and(fn () => (new PerplexitySearchNode)->execute($run, perplexityConfig(['query' => '  ']), []))->toThrow(InvalidArgumentException::class);
    });

    it('asks and reasons at the efforts Perplexity gives those tools, and shapes the answer', function () {
        fakePerplexity(['POST /v1/agent' => perplexityAgentAnswer()]);
        $run = Run::factory()->create();

        $answer = (new PerplexityAskNode)->execute($run, perplexityConfig(['prompt' => 'Why?']), []);

        expect($answer)->toMatchArray(['id' => 'resp_1', 'status' => 'completed', 'finished' => true, 'answer' => 'The answer', 'citations' => ['https://a.io']])
            ->and($answer['sources'][0])->toMatchArray(['title' => 'A', 'url' => 'https://a.io'])
            ->and(webDataBody('POST /v1/agent', ''))->toBe(['input' => 'Why?', 'effort' => 'fast']);

        (new PerplexityReasonNode)->execute($run, perplexityConfig(['prompt' => 'Why?']), []);
        expect(webDataBody('POST /v1/agent', '')['effort'])->toBe('medium');
    });

    it('runs research in the background and reads it by id, checking the id', function () {
        fakePerplexity(['POST /v1/agent' => perplexityAgentAnswer('queued'), 'GET /v1/agent/resp_1' => perplexityAgentAnswer()]);
        $run = Run::factory()->create();

        expect((new PerplexityStartResearchNode)->execute($run, perplexityConfig(['prompt' => 'A report']), []))->toMatchArray(['id' => 'resp_1', 'status' => 'queued', 'finished' => false])
            ->and(webDataBody('POST /v1/agent', ''))->toBe(['input' => 'A report', 'effort' => 'high', 'background' => true])
            ->and((new PerplexityGetAgentResponseNode)->execute($run, perplexityConfig(['response_id' => 'resp_1']), [])['answer'])->toBe('The answer')
            ->and(fn () => (new PerplexityGetAgentResponseNode)->execute($run, perplexityConfig(['response_id' => '../x']), []))->toThrow(InvalidArgumentException::class);
    });

    it('passes a model, an answer shape and tools through to the Agent API', function () {
        fakePerplexity(['POST /v1/agent' => perplexityAgentAnswer()]);

        (new PerplexityCreateAgentResponseNode)->execute(Run::factory()->create(), perplexityConfig(['prompt' => 'Q', 'effort' => 'low', 'model' => 'openai/gpt', 'max_output_tokens' => 50, 'response_schema' => ['type' => 'object'], 'tools' => [['type' => 'web_search']]]), []);

        expect(webDataBody('POST /v1/agent', ''))->toBe(['input' => 'Q', 'effort' => 'low', 'model' => 'openai/gpt', 'max_output_tokens' => 50, 'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'answer', 'schema' => ['type' => 'object']]], 'tools' => [['type' => 'web_search']]]);
    });

    it('cancels a response', function () {
        fakePerplexity(['POST /v1/agent/resp_1/cancel' => ['response_id' => 'resp_1', 'status' => 'cancelling']]);

        expect((new PerplexityCancelAgentResponseNode)->execute(Run::factory()->create(), perplexityConfig(['response_id' => 'resp_1']), []))->toBe(['id' => 'resp_1', 'status' => 'cancelling']);
    });

    it('asks Sonar with a question or a whole conversation, and returns citations', function () {
        fakePerplexity(['POST /v1/sonar' => ['id' => 'c1', 'model' => 'sonar', 'choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']], 'citations' => ['https://a.io'], 'search_results' => [['title' => 'A']]]]);
        $run = Run::factory()->create();

        expect((new PerplexityChatCompletionNode)->execute($run, perplexityConfig(['prompt' => 'Hi', 'system' => 'Be brief', 'temperature' => 1]), []))->toMatchArray(['answer' => 'Hello', 'citations' => ['https://a.io'], 'finish_reason' => 'stop'])
            ->and(webDataBody('POST /v1/sonar', ''))->toBe(['model' => 'sonar', 'messages' => [['role' => 'system', 'content' => 'Be brief'], ['role' => 'user', 'content' => 'Hi']], 'temperature' => 1]);

        (new PerplexityChatCompletionNode)->execute($run, perplexityConfig(['messages' => '[{"role":"user","content":"Q"}]']), []);

        expect(webDataBody('POST /v1/sonar', '')['messages'])->toBe([['role' => 'user', 'content' => 'Q']])
            ->and(fn () => (new PerplexityChatCompletionNode)->execute($run, perplexityConfig(), []))->toThrow(InvalidArgumentException::class);
    });

    it('makes embeddings, and contextual ones from chunks', function () {
        fakePerplexity(['POST /v1/embeddings' => ['data' => [['index' => 0, 'embedding' => 'AAA']], 'model' => 'pplx-embed-v1-0.6b'], 'POST /v1/contextualizedembeddings' => ['data' => [['index' => 0, 'embedding' => 'BBB']]]]);
        $run = Run::factory()->create();

        expect((new PerplexityCreateEmbeddingsNode)->execute($run, perplexityConfig(['input' => ['one', 'two']]), [])['embeddings'])->toBe(['AAA'])
            ->and(webDataBody('POST /v1/embeddings', ''))->toBe(['input' => ['one', 'two'], 'model' => 'pplx-embed-v1-0.6b'])
            ->and((new PerplexityCreateContextualizedEmbeddingsNode)->execute($run, perplexityConfig(['input' => [['a', 'b']]]), [])['embeddings'])->toBe(['BBB'])
            ->and(fn () => (new PerplexityCreateEmbeddingsNode)->execute($run, perplexityConfig(), []))->toThrow(InvalidArgumentException::class);
    });

    it('explains an error with a hint', function (int $status, string $hint) {
        fakePerplexity(['POST /v1/agent' => fn () => Http::response(['error' => ['message' => 'Nope', 'type' => 'x']], $status)]);

        expect(fn () => (new PerplexityAskNode)->execute(Run::factory()->create(), perplexityConfig(['prompt' => 'x']), []))->toThrow(RuntimeException::class, $hint);
    })->with([[401, 'Reconnect Perplexity'], [402, 'out of credit'], [403, 'not allowed'], [429, 'rate limiting']]);
});

describe('perplexity options and connection', function () {
    it('lists the models of each API', function () {
        fakePerplexity(['GET /v1/models' => ['data' => [['id' => 'openai/gpt'], ['id' => 'anthropic/claude']]], 'GET /orchestrator/v1/models' => ['data' => [['id' => 'open/weights']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $source = app(PerplexityOptions::class);

        expect($source->load('perplexity.models', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'pplx-key', 'gpt'))->toArray()['options'])->toBe([['value' => 'openai/gpt', 'label' => 'openai/gpt']])
            ->and($source->load('perplexity.orchestrator_models', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'pplx-key'))->toArray()['options'][0]['value'])->toBe('open/weights');
    });

    it('checks the key against something that needs one', function () {
        fakePerplexity(['GET /v1/batches' => ['data' => []]]);
        expect(webDataCredentialCheck('perplexity')['ok'])->toBeTrue();

        fakePerplexity(['GET /v1/batches' => fn () => Http::response(['error' => ['message' => 'bad']], 401)]);
        expect(webDataCredentialCheck('perplexity')['ok'])->toBeFalse();
    });
});
