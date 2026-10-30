<?php

namespace App\Nodes\Integrations\Apify;

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
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Apify node
 * family (API v2, the token as a bearer header). Answers are wrapped in `data`, lists
 * in `data.items`. An Actor or task may be named by its id or as `username~name`; a
 * `/` is read as the `~` Apify wants. A run can take hours, so nothing here waits
 * longer than `MAX_WAIT_SECONDS`: a run still going by then is handed back by id, for
 * the Get Run and Get Dataset Items nodes or a Run Finished trigger.
 */
abstract class AbstractApifyNode implements DeclaresEffect, NodeContract
{
    use ReadsApiSettings;
    use ResolvesConnectorCredential;

    protected const string API_URL = 'https://api.apify.com/v2';

    /**
     * The longest a step waits for a run.
     */
    protected const int MAX_WAIT_SECONDS = 60;

    /**
     * @var list<string>
     */
    protected const array FINISHED_STATUSES = ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'];

    public function category(): string
    {
        return 'apify';
    }

    /**
     * Call Apify and return the decoded body, without its `data` wrapper (an empty
     * array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<int|string, mixed>|null  $body
     * @param  array<string, mixed>  $query  null values are dropped
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException
     */
    protected function apify(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = [], int $timeout = 30): array
    {
        $response = $this->apifyResponse($run, $config, $method, $path, $body, $query, $timeout);
        $decoded = $response->json();

        if (! is_array($decoded)) {
            return [];
        }

        return array_key_exists('data', $decoded) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int|string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     *
     * @throws RuntimeException
     */
    protected function apifyResponse(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = [], int $timeout = 30): Response
    {
        $options = ['query' => array_map(fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, array_filter($query, fn (mixed $value): bool => $value !== null))];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        $response = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout($timeout)->send(strtoupper($method), self::API_URL.$path, $options);

        $this->assertOk($response);

        return $response;
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
            $response->status() === 401 => ' Reconnect Apify: the API token was refused.',
            $response->status() === 402 => ' The Apify account is out of credit, or its plan does not include this.',
            $response->status() === 403 => ' The Apify token is not allowed to do that (a scoped token may need more access).',
            $response->status() === 429 => ' Apify is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Apify error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * An Actor or task named in a URL: its id, or `username~name`.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function resourceId(array $config, string $key, string $label): string
    {
        return $this->pathId([$key => str_replace('/', '~', trim((string) ($config[$key] ?? '')))], $key, $label);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function actorId(array $config): string
    {
        return $this->resourceId($config, 'actor_id', 'The Actor');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function taskId(array $config): string
    {
        return $this->resourceId($config, 'task_id', 'The task');
    }

    /**
     * The items of a list, as far as `$limit` goes.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listItems(Run $run, array $config, string $path, array $query = [], ?int $limit = null): array
    {
        $page = $this->apify($run, $config, 'GET', $path, query: [...$query, 'limit' => $limit ?? $this->limit($config, 50, 1000), 'desc' => true]);

        return array_values(array_filter((array) ($page['items'] ?? []), 'is_array'));
    }

    /**
     * What a run's settings add to its address.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function runOptions(array $config): array
    {
        return [
            'timeout' => $this->intOf($config, 'run_timeout'),
            'memory' => $this->intOf($config, 'memory'),
            'maxItems' => $this->intOf($config, 'max_items'),
            'maxTotalChargeUsd' => $this->floatOf($config, 'max_charge_usd'),
            'build' => $this->textOf($config, 'build'),
        ];
    }

    /**
     * The settings every way of starting a run has.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function runFields(): array
    {
        return [
            'input' => Field::json('Input', 'The run\'s input. Leave empty to use the saved one.'),
            'run_timeout' => Field::advanced(Field::integer('Run time limit (seconds)', 'Leave empty for the default.', null, 0)),
            'memory' => Field::advanced(Field::integer('Memory (MB)', 'A power of 2, from 128.', null, 128)),
            'max_items' => Field::advanced(Field::integer('Max results to pay for', 'For Actors charged per result.', null, 1)),
            'max_charge_usd' => Field::advanced(Field::integer('Max cost (USD)', 'For Actors charged per event.', null, 0)),
            'build' => Field::advanced(Field::text('Build', 'A tag or number. Leave empty for the default.', 'latest')),
        ];
    }

    /**
     * Start a run and, if asked, wait a little for it.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function startRun(Run $run, array $config, string $path): array
    {
        $wait = min(self::MAX_WAIT_SECONDS, max(0, (int) ($config['wait_seconds'] ?? 0)));
        $input = $this->decoded($config['input'] ?? null, 'The input');

        // No body means the Actor's or task's own saved input.
        return $this->apify($run, $config, 'POST', $path, $input, [...$this->runOptions($config), 'waitForFinish' => $wait > 0 ? $wait : null], self::MAX_WAIT_SECONDS + 15);
    }

    /**
     * Start a run, wait up to the limit, and read the results if it finished.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function runAndRead(Run $run, array $config, string $path): array
    {
        $started = $this->startRun($run, [...$config, 'wait_seconds' => $config['wait_seconds'] ?? self::MAX_WAIT_SECONDS], $path);
        $shaped = $this->shapeRun($started);

        if (! $shaped['finished']) {
            return [...$shaped, 'items' => [], 'count' => 0, 'more_coming' => true];
        }

        $items = $shaped['status'] === 'SUCCEEDED' && filled($shaped['dataset_id'])
            ? $this->datasetItems($run, $config, $this->pathId(['id' => $shaped['dataset_id']], 'id', 'The dataset'))
            : [];

        return [...$shaped, 'items' => $items, 'count' => count($items), 'more_coming' => false];
    }

    /**
     * The fields of the "run and read the results" nodes.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function runAndReadFields(): array
    {
        return [
            ...$this->runFields(),
            'wait_seconds' => Field::integer('Wait up to (seconds)', 'If the run is still going by then, the step ends with its id: read the results later with Get Dataset Items.', self::MAX_WAIT_SECONDS, 1, self::MAX_WAIT_SECONDS),
            ...$this->datasetReadFields(),
        ];
    }

    /**
     * The fields that shape a dataset read.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function datasetReadFields(): array
    {
        return [
            'limit' => Field::integer('Max results', null, 100, 1, 1000),
            'offset' => Field::advanced(Field::integer('Skip', null, null, 0)),
            'fields' => Field::advanced(Field::list('Only these fields', null, 'title, url')),
            'omit' => Field::advanced(Field::list('Leave out fields')),
            'clean' => Field::advanced(Field::boolean('Only clean results', 'Leaves out empty items and hidden fields.')),
            'newest_first' => Field::advanced(Field::boolean('Newest first')),
        ];
    }

    /**
     * Read a dataset's items.
     *
     * @param  array<string, mixed>  $config
     * @return list<mixed>
     */
    protected function datasetItems(Run $run, array $config, string $datasetId): array
    {
        return array_values((array) $this->apifyResponse($run, $config, 'GET', "/datasets/{$datasetId}/items", query: $this->datasetQuery($config))->json());
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function datasetQuery(array $config): array
    {
        $fields = $this->stringList($config['fields'] ?? []);
        $omit = $this->stringList($config['omit'] ?? []);

        return [
            'format' => 'json',
            'limit' => $this->limit($config, 100, 1000),
            'offset' => $this->intOf($config, 'offset'),
            'fields' => $fields === [] ? null : implode(',', $fields),
            'omit' => $omit === [] ? null : implode(',', $omit),
            'clean' => $this->boolOf($config, 'clean'),
            'desc' => $this->boolOf($config, 'newest_first'),
        ];
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<string, mixed>
     */
    protected function shapeRun(array $run): array
    {
        return [
            'id' => $run['id'] ?? null,
            'actor_id' => $run['actId'] ?? null,
            'task_id' => $run['actorTaskId'] ?? null,
            'status' => $run['status'] ?? null,
            'finished' => in_array($run['status'] ?? null, self::FINISHED_STATUSES, true),
            'status_message' => $run['statusMessage'] ?? null,
            'started_at' => $run['startedAt'] ?? null,
            'finished_at' => $run['finishedAt'] ?? null,
            'dataset_id' => $run['defaultDatasetId'] ?? null,
            'key_value_store_id' => $run['defaultKeyValueStoreId'] ?? null,
            'request_queue_id' => $run['defaultRequestQueueId'] ?? null,
            'build_number' => $run['buildNumber'] ?? null,
            'origin' => data_get($run, 'meta.origin'),
            'cost_usd' => $run['usageTotalUsd'] ?? null,
            'console_url' => $run['consoleUrl'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $build
     * @return array<string, mixed>
     */
    protected function shapeBuild(array $build): array
    {
        return [
            'id' => $build['id'] ?? null,
            'actor_id' => $build['actId'] ?? null,
            'status' => $build['status'] ?? null,
            'finished' => in_array($build['status'] ?? null, self::FINISHED_STATUSES, true),
            'build_number' => $build['buildNumber'] ?? null,
            'started_at' => $build['startedAt'] ?? null,
            'finished_at' => $build['finishedAt'] ?? null,
        ];
    }

    /**
     * Which runs a list asks for: `finished` is any ending, `any` is every run.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function wantedStatuses(array $config): array
    {
        return match ($config['status'] ?? 'finished') {
            'any' => [],
            'finished', '' => self::FINISHED_STATUSES,
            default => [(string) $config['status']],
        };
    }

    /**
     * The newest runs at an address, narrowed to the wanted endings.
     *
     * @param  array<string, mixed>  $config
     * @return array{runs: list<array<string, mixed>>, count: int}
     */
    protected function readRuns(Run $run, array $config, string $path): array
    {
        $statuses = $this->wantedStatuses($config);
        $items = $this->listItems($run, $config, $path, ['status' => $statuses === [] ? null : implode(',', $statuses)]);

        if ($statuses !== []) {
            $items = array_values(array_filter($items, fn (array $item): bool => in_array($item['status'] ?? null, $statuses, true)));
        }

        return ['runs' => array_map($this->shapeRun(...), $items), 'count' => count($items)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function statusField(): array
    {
        return Field::select('Outcome', ['finished' => 'Finished, any way', 'SUCCEEDED' => 'Succeeded', 'FAILED' => 'Failed', 'ABORTED' => 'Aborted', 'TIMED-OUT' => 'Timed out', 'any' => 'Every run, finished or not'], 'As a trigger, "every run" fires when a run starts.', 'finished');
    }

    /**
     * @return array<string, mixed>
     */
    protected function actorField(string $title = 'Actor', string $source = 'apify.actors'): array
    {
        return Field::dynamic($title, $source, 'Your Actors. Or type any Actor as username/name.', 'apify/web-scraper');
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskField(): array
    {
        return Field::dynamic('Task', 'apify.tasks', 'Or type a task as username/name.', 'username/my-task');
    }

    /**
     * @return array<string, mixed>
     */
    protected function datasetField(): array
    {
        return Field::dynamic('Dataset', 'apify.datasets', 'A run\'s results are in its dataset_id.', 'Pick a dataset.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function storeField(): array
    {
        return Field::dynamic('Key-value store', 'apify.key_value_stores', 'A run\'s output is in its key_value_store_id.', 'Pick a store.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function scheduleField(): array
    {
        return Field::dynamic('Schedule', 'apify.schedules', null, 'Pick a schedule.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function webhookField(): array
    {
        return Field::dynamic('Webhook', 'apify.webhooks', null, 'Pick a webhook.');
    }

    /**
     * The fields of a saved task.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function taskFields(): array
    {
        return [
            'title' => Field::text('Title'),
            'description' => Field::advanced(Field::textarea('Description')),
            'input' => Field::json('Input', 'The input the task runs with.'),
            'run_timeout' => Field::advanced(Field::integer('Run time limit (seconds)', null, null, 0)),
            'memory' => Field::advanced(Field::integer('Memory (MB)', null, null, 128)),
            'max_items' => Field::advanced(Field::integer('Max results to pay for', null, null, 1)),
            'build' => Field::advanced(Field::text('Build', null, 'latest')),
        ];
    }

    /**
     * A task's fields, as Apify names them.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function taskBody(array $config): array
    {
        return $this->withoutEmpty([
            'name' => $this->textOf($config, 'name'),
            'title' => $this->textOf($config, 'title'),
            'description' => $this->textOf($config, 'description'),
            'input' => $this->decoded($config['input'] ?? null, 'The input'),
            'options' => $this->withoutEmpty(['timeoutSecs' => $this->intOf($config, 'run_timeout'), 'memoryMbytes' => $this->intOf($config, 'memory'), 'maxItems' => $this->intOf($config, 'max_items'), 'build' => $this->textOf($config, 'build')]),
        ]);
    }

    /**
     * The fields of a schedule.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function scheduleFields(): array
    {
        return [
            'title' => Field::advanced(Field::text('Title')),
            'description' => Field::advanced(Field::textarea('Description')),
            'cron' => Field::text('When (cron)', null, '0 9 * * *'),
            'timezone' => Field::advanced(Field::text('Time zone', 'An IANA name. Leave empty for UTC.', 'Europe/Berlin')),
            'enabled' => Field::boolean('On', null, true),
            'exclusive' => Field::advanced(Field::boolean('Skip if the last run is still going')),
            'actions' => Field::json('Run these', 'A list of actions: {"type": "RUN_ACTOR", "actorId": "..."} or {"type": "RUN_ACTOR_TASK", "actorTaskId": "..."}.', 'array'),
        ];
    }

    /**
     * A schedule's fields, as Apify names them.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function scheduleBody(array $config): array
    {
        return $this->withoutEmpty([
            'name' => $this->textOf($config, 'name'),
            'title' => $this->textOf($config, 'title'),
            'description' => $this->textOf($config, 'description'),
            'cronExpression' => $this->textOf($config, 'cron'),
            'timezone' => $this->textOf($config, 'timezone'),
            'isEnabled' => $this->boolOf($config, 'enabled'),
            'isExclusive' => $this->boolOf($config, 'exclusive'),
            'actions' => $this->decoded($config['actions'] ?? null, 'The actions'),
        ]);
    }

    /**
     * The fields of a webhook.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function webhookFields(): array
    {
        return [
            'request_url' => Field::text('Address', 'Where Apify posts the event.', 'https://'),
            'event_types' => Field::list('Events', 'ACTOR.RUN.SUCCEEDED, ACTOR.RUN.FAILED, ACTOR.RUN.ABORTED, ACTOR.RUN.TIMED_OUT, ACTOR.RUN.CREATED, ACTOR.RUN.RESURRECTED, or the same for ACTOR.BUILD.', 'ACTOR.RUN.SUCCEEDED'),
            'actor_id' => Field::advanced($this->actorField('For Actor')),
            'task_id' => Field::advanced($this->taskField()),
            'description' => Field::advanced(Field::text('Description')),
            'payload_template' => Field::advanced(Field::textarea('Payload template', 'Leave empty for Apify\'s default.')),
        ];
    }

    /**
     * A webhook's fields, as Apify names them.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function webhookBody(array $config): array
    {
        $condition = $this->withoutEmpty([
            'actorId' => filled($config['actor_id'] ?? null) ? $this->actorId($config) : null,
            'actorTaskId' => filled($config['task_id'] ?? null) ? $this->taskId($config) : null,
        ]);

        return $this->withoutEmpty([
            'requestUrl' => filled($config['request_url'] ?? null) ? $this->webUrl($config, 'request_url', 'The address') : null,
            'eventTypes' => $this->stringList($config['event_types'] ?? []),
            'condition' => $condition,
            'description' => $this->textOf($config, 'description'),
            'payloadTemplate' => $this->textOf($config, 'payload_template'),
        ]);
    }
}
