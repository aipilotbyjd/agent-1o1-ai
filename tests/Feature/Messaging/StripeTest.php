<?php

use App\Enums\Agents\ActionEffect;
use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderEvent;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Stripe\AbstractStripeNode;
use App\Nodes\Integrations\Stripe\StripeCancelSubscriptionNode;
use App\Nodes\Integrations\Stripe\StripeCreateCheckoutSessionNode;
use App\Nodes\Integrations\Stripe\StripeCreateCustomerNode;
use App\Nodes\Integrations\Stripe\StripeCreatePaymentIntentNode;
use App\Nodes\Integrations\Stripe\StripeCreatePriceNode;
use App\Nodes\Integrations\Stripe\StripeCreateRefundNode;
use App\Nodes\Integrations\Stripe\StripeDeleteCustomerNode;
use App\Nodes\Integrations\Stripe\StripeGetCustomerNode;
use App\Nodes\Integrations\Stripe\StripeGetEventNode;
use App\Nodes\Integrations\Stripe\StripeListCustomersNode;
use App\Nodes\Integrations\Stripe\StripeListEventsNode;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Triggers\Push\StripeSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function stripeConfig(array $config = []): array
{
    return ['access_token' => 'sk_test_123', ...$config];
}

/**
 * Answer Stripe. A key is "METHOD /v1/path"; the value is the JSON body, a closure
 * taking the request, or a ready response. A route nobody answers is a 404, so a
 * node that calls something unexpected fails the test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeStripe(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*stripe.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['error' => ['type' => 'invalid_request_error', 'message' => "Nothing fakes {$key}"]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
    }]);
}

/**
 * The request that went to a route: its form body or query, decoded the way Stripe reads it.
 *
 * @return array<int|string, mixed>
 */
function stripeSent(string $route): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH) === $route));

    expect($found)->toHaveCount(1);

    parse_str($found[0][0]->method() === 'GET' ? (string) parse_url($found[0][0]->url(), PHP_URL_QUERY) : $found[0][0]->body(), $params);

    return $params;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function stripeEvent(string $id = 'evt_1', string $type = 'payment_intent.succeeded', array $extra = []): array
{
    return [
        'id' => $id, 'object' => 'event', 'type' => $type, 'created' => 1772359200, 'livemode' => false, 'api_version' => '2025-01-27',
        'request' => ['id' => 'req_1'],
        'data' => ['object' => ['id' => 'pi_1', 'object' => 'payment_intent', 'amount' => 1999, 'currency' => 'usd'], 'previous_attributes' => ['status' => 'processing']],
        ...$extra,
    ];
}

describe('stripe nodes', function () {
    it('registers the nodes under the stripe connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'stripe_'));

        expect(count($types))->toBeGreaterThanOrEqual(36);

        foreach ($types as $type) {
            $node = $registry->resolve($type);

            expect($node)->toBeInstanceOf(AbstractStripeNode::class)
                ->and($node->category())->toBe('stripe')
                ->and($node->configSchema()['properties'])->toHaveKey('credential_id');
        }
    });

    it('creates a customer with a form body, nested metadata and the idempotency key', function () {
        fakeStripe(['POST /v1/customers' => ['id' => 'cus_1', 'email' => 'ada@example.com']]);

        $output = (new StripeCreateCustomerNode)->execute(Run::factory()->create(), stripeConfig([
            'email' => 'ada@example.com', 'name' => 'Ada', 'metadata' => ['order' => 42, 'vip' => true], 'idempotency_key' => 'order-42',
        ]), []);

        expect($output['id'])->toBe('cus_1')
            ->and(stripeSent('POST /v1/customers'))->toBe(['email' => 'ada@example.com', 'name' => 'Ada', 'metadata' => ['order' => '42', 'vip' => 'true']]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk_test_123') && $request->hasHeader('Idempotency-Key', 'order-42') && $request->isForm());
    });

    it('only sends an idempotency key when one is given', function () {
        fakeStripe(['POST /v1/customers' => ['id' => 'cus_1']]);

        (new StripeCreateCustomerNode)->execute(Run::factory()->create(), stripeConfig(['email' => 'ada@example.com']), []);

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Idempotency-Key'));
    });

    it('lists customers page by page up to the limit', function () {
        $calls = [];
        fakeStripe(['GET /v1/customers' => function (Request $request) use (&$calls) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $calls[] = $query;

            return isset($query['starting_after'])
                ? ['has_more' => false, 'data' => [['id' => 'cus_3']]]
                : ['has_more' => true, 'data' => [['id' => 'cus_1'], ['id' => 'cus_2']]];
        }]);

        $output = (new StripeListCustomersNode)->execute(Run::factory()->create(), stripeConfig(['email' => 'ada@example.com', 'since' => '2026-03-01 00:00:00', 'limit' => 50]), []);

        expect(array_column($output['customers'], 'id'))->toBe(['cus_1', 'cus_2', 'cus_3'])
            ->and($output)->toMatchArray(['count' => 3, 'has_more' => false])
            ->and($calls)->toHaveCount(2)
            ->and($calls[0])->toMatchArray(['email' => 'ada@example.com', 'limit' => '50'])
            ->and($calls[0]['created']['gte'])->toBe((string) strtotime('2026-03-01 00:00:00'))
            ->and($calls[1]['starting_after'])->toBe('cus_2');
    });

    it('says when there is more than the limit', function () {
        fakeStripe(['GET /v1/customers' => ['has_more' => true, 'data' => [['id' => 'cus_1'], ['id' => 'cus_2']]]]);

        $output = (new StripeListCustomersNode)->execute(Run::factory()->create(), stripeConfig(['limit' => 2]), []);

        expect($output['count'])->toBe(2)->and($output['has_more'])->toBeTrue();
    });

    it('checks ids, amounts and currencies before calling Stripe', function () {
        fakeStripe([]);
        $run = Run::factory()->create();

        expect(fn () => (new StripeGetCustomerNode)->execute($run, stripeConfig(['customer_id' => 'pi_123']), []))->toThrow(InvalidArgumentException::class, 'not a Stripe customer id')
            ->and(fn () => (new StripeGetCustomerNode)->execute($run, stripeConfig(['customer_id' => 'cus_1/../account']), []))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new StripeGetCustomerNode)->execute($run, stripeConfig([]), []))->toThrow(InvalidArgumentException::class, 'required')
            ->and(fn () => (new StripeCreatePaymentIntentNode)->execute($run, stripeConfig(['amount' => 19.99, 'currency' => 'usd']), []))->toThrow(InvalidArgumentException::class, 'smallest unit')
            ->and(fn () => (new StripeCreatePaymentIntentNode)->execute($run, stripeConfig(['amount' => 1999, 'currency' => 'dollars']), []))->toThrow(InvalidArgumentException::class, 'three-letter')
            ->and(fn () => (new StripeCreatePaymentIntentNode)->execute($run, stripeConfig(['amount' => 1999, 'currency' => 'usd', 'confirm' => true]), []))->toThrow(InvalidArgumentException::class, 'needs a payment method');

        Http::assertNothingSent();
    });

    it('creates a payment intent with the amount in cents and a lower-case currency', function () {
        fakeStripe(['POST /v1/payment_intents' => ['id' => 'pi_1', 'status' => 'requires_payment_method']]);

        (new StripeCreatePaymentIntentNode)->execute(Run::factory()->create(), stripeConfig(['amount' => '1999', 'currency' => 'USD', 'customer_id' => 'cus_1']), []);

        expect(stripeSent('POST /v1/payment_intents'))->toBe(['amount' => '1999', 'currency' => 'usd', 'customer' => 'cus_1', 'automatic_payment_methods' => ['enabled' => 'true']]);
    });

    it('confirms a payment without redirects and declares it as reaching the customer', function () {
        fakeStripe(['POST /v1/payment_intents' => ['id' => 'pi_1', 'status' => 'succeeded']]);
        $node = new StripeCreatePaymentIntentNode;
        $config = stripeConfig(['amount' => 500, 'currency' => 'eur', 'payment_method' => 'pm_1', 'confirm' => true]);

        $node->execute(Run::factory()->create(), $config, []);

        expect(stripeSent('POST /v1/payment_intents'))->toMatchArray(['confirm' => 'true', 'payment_method' => 'pm_1', 'automatic_payment_methods' => ['enabled' => 'true', 'allow_redirects' => 'never']])
            ->and($node->effect($config))->toBe(ActionEffect::External)
            ->and($node->effect(stripeConfig(['amount' => 500, 'currency' => 'eur'])))->toBe(ActionEffect::Write);
    });

    it('refunds a payment intent or a charge, not both and not neither', function () {
        fakeStripe(['POST /v1/refunds' => ['id' => 're_1', 'status' => 'succeeded']]);
        $run = Run::factory()->create();
        $node = new StripeCreateRefundNode;

        $node->execute($run, stripeConfig(['payment_intent_id' => 'pi_1', 'amount' => 500, 'reason' => 'duplicate', 'idempotency_key' => 'refund-order-1']), []);

        expect(stripeSent('POST /v1/refunds'))->toBe(['payment_intent' => 'pi_1', 'amount' => '500', 'reason' => 'duplicate'])
            ->and(fn () => $node->execute($run, stripeConfig([]), []))->toThrow(InvalidArgumentException::class, 'either')
            ->and(fn () => $node->execute($run, stripeConfig(['payment_intent_id' => 'pi_1', 'charge_id' => 'ch_1']), []))->toThrow(InvalidArgumentException::class, 'either')
            ->and($node->effect([]))->toBe(ActionEffect::External);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', 'refund-order-1'));
    });

    it('creates a recurring price and a one-time one', function () {
        fakeStripe(['POST /v1/prices' => ['id' => 'price_1']]);
        $node = new StripeCreatePriceNode;

        $node->execute(Run::factory()->create(), stripeConfig(['product_id' => 'prod_1', 'unit_amount' => 900, 'currency' => 'usd', 'interval' => 'month']), []);
        expect(stripeSent('POST /v1/prices'))->toBe(['product' => 'prod_1', 'unit_amount' => '900', 'currency' => 'usd', 'recurring' => ['interval' => 'month']]);

        fakeStripe(['POST /v1/prices' => ['id' => 'price_2']]);
        $node->execute(Run::factory()->create(), stripeConfig(['product_id' => 'prod_1', 'unit_amount' => 900, 'currency' => 'usd']), []);
        expect(stripeSent('POST /v1/prices'))->not->toHaveKey('recurring');
    });

    it('cancels a subscription at the end of the period or right now', function () {
        fakeStripe(['POST /v1/subscriptions/sub_1' => ['id' => 'sub_1', 'cancel_at_period_end' => true], 'DELETE /v1/subscriptions/sub_1' => ['id' => 'sub_1', 'status' => 'canceled']]);
        $node = new StripeCancelSubscriptionNode;

        $later = $node->execute(Run::factory()->create(), stripeConfig(['subscription_id' => 'sub_1']), []);
        $now = $node->execute(Run::factory()->create(), stripeConfig(['subscription_id' => 'sub_1', 'when' => 'now']), []);

        expect(stripeSent('POST /v1/subscriptions/sub_1'))->toBe(['cancel_at_period_end' => 'true'])
            ->and($later['cancel_at_period_end'])->toBeTrue()
            ->and($now['status'])->toBe('canceled')
            ->and($node->effect([]))->toBe(ActionEffect::Destructive);
    });

    it('deletes a customer and says so', function () {
        fakeStripe(['DELETE /v1/customers/cus_1' => ['id' => 'cus_1', 'deleted' => true]]);

        expect((new StripeDeleteCustomerNode)->execute(Run::factory()->create(), stripeConfig(['customer_id' => 'cus_1']), []))->toBe(['deleted' => true, 'customer_id' => 'cus_1'])
            ->and((new StripeDeleteCustomerNode)->effect([]))->toBe(ActionEffect::Destructive);
    });

    it('makes a checkout session from price lines and leaves the email out when there is a customer', function () {
        fakeStripe(['POST /v1/checkout/sessions' => ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']]);
        $node = new StripeCreateCheckoutSessionNode;
        $run = Run::factory()->create();

        $output = $node->execute($run, stripeConfig([
            'line_items' => '[{"price":"price_1","quantity":2}]', 'success_url' => 'https://example.com/thanks', 'customer_id' => 'cus_1', 'customer_email' => 'ada@example.com',
        ]), []);

        expect($output['url'])->toStartWith('https://checkout.stripe.com')
            ->and(stripeSent('POST /v1/checkout/sessions'))->toBe(['mode' => 'payment', 'line_items' => [['price' => 'price_1', 'quantity' => '2']], 'success_url' => 'https://example.com/thanks', 'customer' => 'cus_1'])
            ->and(fn () => $node->execute($run, stripeConfig(['line_items' => '[]', 'success_url' => 'https://example.com']), []))->toThrow(InvalidArgumentException::class, 'at least one line item')
            ->and(fn () => $node->execute($run, stripeConfig(['line_items' => '[{"price":"prod_1"}]', 'success_url' => 'https://example.com']), []))->toThrow(InvalidArgumentException::class, 'price id');
    });

    it('turns a Stripe error into a sentence, with a hint for a refused or limited key', function () {
        fakeStripe(['GET /v1/customers/cus_1' => fn () => Http::response(['error' => ['type' => 'invalid_request_error', 'message' => 'No such customer: cus_1', 'param' => 'id']], 404)]);
        expect(fn () => (new StripeGetCustomerNode)->execute(Run::factory()->create(), stripeConfig(['customer_id' => 'cus_1']), []))->toThrow(RuntimeException::class, 'Stripe error: No such customer: cus_1 (field: id).');

        fakeStripe(['GET /v1/customers/cus_1' => fn () => Http::response(['error' => ['message' => 'Invalid API Key provided']], 401)]);
        expect(fn () => (new StripeGetCustomerNode)->execute(Run::factory()->create(), stripeConfig(['customer_id' => 'cus_1']), []))->toThrow(RuntimeException::class, 'Reconnect Stripe');

        fakeStripe(['GET /v1/customers/cus_1' => fn () => Http::response(['error' => ['message' => 'The provided key does not have the required permissions']], 403)]);
        expect(fn () => (new StripeGetCustomerNode)->execute(Run::factory()->create(), stripeConfig(['customer_id' => 'cus_1']), []))->toThrow(RuntimeException::class, 'restricted key');
    });

    it('gets an event with the object it is about next to it', function () {
        fakeStripe(['GET /v1/events/evt_1' => stripeEvent()]);

        $event = (new StripeGetEventNode)->execute(Run::factory()->create(), stripeConfig(['event_id' => 'evt_1']), []);

        expect($event)->toMatchArray([
            'id' => 'evt_1', 'type' => 'payment_intent.succeeded', 'created_at' => '2026-03-01T10:00:00+00:00', 'livemode' => false, 'request_id' => 'req_1',
            'object_type' => 'payment_intent', 'object_id' => 'pi_1', 'previous_attributes' => ['status' => 'processing'],
        ])->and($event['object']['amount'])->toBe(1999);
    });
});

describe('stripe events', function () {
    it('asks Stripe for one event type, many, or filters itself when it can\'t', function () {
        $node = new StripeListEventsNode;
        $run = Run::factory()->create();

        fakeStripe(['GET /v1/events' => ['has_more' => false, 'data' => [stripeEvent('evt_1', 'invoice.paid')]]]);
        $node->execute($run, stripeConfig(['types' => ['invoice.paid']]), []);
        expect(stripeSent('GET /v1/events'))->toMatchArray(['type' => 'invoice.paid', 'limit' => '25'])->not->toHaveKey('types');

        fakeStripe(['GET /v1/events' => ['has_more' => false, 'data' => [stripeEvent('evt_1', 'invoice.paid')]]]);
        $node->execute($run, stripeConfig(['types' => 'invoice.paid, invoice.payment_failed']), []);
        expect(stripeSent('GET /v1/events'))->toMatchArray(['types' => ['invoice.paid', 'invoice.payment_failed']])->not->toHaveKey('type');

        fakeStripe(['GET /v1/events' => ['has_more' => false, 'data' => [stripeEvent('evt_1', 'invoice.paid'), stripeEvent('evt_2', 'customer.created'), stripeEvent('evt_3', 'customer.deleted')]]]);
        $output = $node->execute($run, stripeConfig(['types' => ['invoice.paid', 'customer.*']]), []);
        expect(stripeSent('GET /v1/events'))->not->toHaveKeys(['type', 'types'])
            ->and(array_column($output['events'], 'id'))->toBe(['evt_1', 'evt_2', 'evt_3']);
    });

    it('drops events the names don\'t match, even if Stripe sent them', function () {
        fakeStripe(['GET /v1/events' => ['has_more' => false, 'data' => [stripeEvent('evt_1', 'invoice.paid'), stripeEvent('evt_2', 'customer.created')]]]);

        $output = (new StripeListEventsNode)->execute(Run::factory()->create(), stripeConfig(['types' => ['invoice.paid', 'invoice.voided']]), []);

        expect(array_column($output['events'], 'id'))->toBe(['evt_1'])->and($output['count'])->toBe(1);
    });

    it('reads event names from a list or a comma-separated line and ignores what isn\'t a name', function () {
        $node = new StripeListEventsNode;

        expect($node->eventTypes(['types' => [' invoice.paid ', 'invoice.paid', '', '*', 'drop table', 'customer.*']]))->toBe(['invoice.paid', 'customer.*'])
            ->and($node->eventTypes(['types' => 'a.b, c.d']))->toBe(['a.b', 'c.d'])
            ->and($node->eventTypes([]))->toBe([]);
    });
});

/**
 * A published workflow whose first step is the event trigger, with its
 * subscription synced against a faked Stripe.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function stripeTrigger(array $nodeConfig = ['types' => ['invoice.paid']], array $routes = [], bool $sync = true, ?ConnectorCredential $credential = null): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeStripe($routes + ['POST /v1/webhook_endpoints' => fn () => stripeEndpoint()]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential ??= ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'stripe') ?? Connector::factory()->create(['key' => 'stripe']))
        ->create(['data' => ['api_key' => 'sk_test_123'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => 'stripe_list_events', 'config' => ['credential_id' => $credential->id, 'activate' => true, ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

    if ($sync) {
        SyncTriggerSubscription::dispatchSync($trigger->id);
    }

    return $trigger->fresh();
}

/**
 * A webhook endpoint as Stripe returns it when it is made.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function stripeEndpoint(string $id = 'we_1', string $secret = 'whsec_test_secret', array $extra = []): array
{
    return ['id' => $id, 'object' => 'webhook_endpoint', 'secret' => $secret, 'status' => 'enabled', ...$extra];
}

/**
 * A delivery as Stripe sends it, signed with the endpoint's secret.
 */
function stripeDelivery(string $secret, string $eventId = 'evt_1', ?int $at = null, bool $tamper = false, ?string $header = null)
{
    $body = json_encode(stripeEvent($eventId, 'invoice.paid'));
    $at ??= time();
    $signature = hash_hmac('sha256', "{$at}.{$body}", $secret);

    return test()->call('POST', '/api/hooks/apps/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $header ?? "t={$at},v1={$signature}",
    ], $tamper ? $body.' ' : $body);
}

describe('stripe triggers', function () {
    it('starts one run for each new event, once, after a baseline', function () {
        $trigger = stripeTrigger(sync: false);
        $poll = fn (array $events) => fakeStripe(['GET /v1/events' => ['has_more' => false, 'data' => $events]]);

        $poll([stripeEvent('evt_1', 'invoice.paid')]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([stripeEvent('evt_3', 'invoice.paid'), stripeEvent('evt_2', 'invoice.paid'), stripeEvent('evt_1', 'invoice.paid')]);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        $events = $trigger->events()->where('status', 'queued')->get();

        expect($events->pluck('payload.id')->sort()->values()->all())->toBe(['evt_2', 'evt_3'])
            ->and($events->first()->payload)->toMatchArray(['type' => 'invoice.paid', 'object_type' => 'payment_intent', 'object_id' => 'pi_1']);

        // The webhook and the next poll see the same events again: nothing more runs.
        dispatch_sync(new PollTrigger($trigger->fresh()));
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->count())->toBe(2);
    });

    it('registers one endpoint for the named events, keeps its secret, and shares it between triggers with the same events', function () {
        $trigger = stripeTrigger(['types' => ['invoice.paid', 'customer.created']]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->provider)->toBe('stripe')
            ->and($watch->external_id)->toBe('we_1')
            ->and($watch->secret)->toBe('whsec_test_secret')
            ->and($watch->meta['events'])->toBe(['invoice.paid', 'customer.created'])
            ->and($trigger->subscription->routing_key)->toBe('we_1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(stripeSent('POST /v1/webhook_endpoints'))->toBe(['url' => url('/api/hooks/apps/stripe'), 'enabled_events' => ['invoice.paid', 'customer.created'], 'description' => 'Flows triggers']);

        $source = app(StripeSource::class);
        $same = new Trigger(['config' => ['node_type' => 'stripe_list_events', 'node_config' => ['types' => ['customer.created', 'invoice.paid']]]]);
        $other = new Trigger(['config' => ['node_type' => 'stripe_list_events', 'node_config' => ['types' => ['invoice.paid']]]]);

        expect($source->watchKeyFor($same))->toBe($watch->resource_key)
            ->and($source->watchKeyFor($other))->not->toBe($watch->resource_key);
    });

    it('listens to every event when none is named', function () {
        stripeTrigger(['types' => []]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('events:*')
            ->and(stripeSent('POST /v1/webhook_endpoints')['enabled_events'])->toBe(['*']);
    });

    it('starts a poll when a signed event arrives, and only once for the same event', function () {
        stripeTrigger();
        $secret = (string) ProviderWatch::query()->sole()->secret;

        stripeDelivery($secret, 'evt_9')->assertOk();
        stripeDelivery($secret, 'evt_9')->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);

        stripeDelivery($secret, 'evt_10')->assertOk();

        Queue::assertPushed(PollTrigger::class, 2);
    });

    it('accepts a delivery signed with either secret while one is being rolled', function () {
        stripeTrigger();
        $secret = (string) ProviderWatch::query()->sole()->secret;
        $body = json_encode(stripeEvent('evt_9', 'invoice.paid'));
        $at = time();
        $old = hash_hmac('sha256', "{$at}.{$body}", 'whsec_old');
        $current = hash_hmac('sha256', "{$at}.{$body}", $secret);

        test()->call('POST', '/api/hooks/apps/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$at},v1={$old},v1={$current}"], $body)->assertOk();
    });

    it('refuses a wrong secret, an altered body, an old timestamp and a missing or broken signature', function () {
        stripeTrigger();
        $secret = (string) ProviderWatch::query()->sole()->secret;

        stripeDelivery('whsec_not_it')->assertUnauthorized();
        stripeDelivery($secret, tamper: true)->assertUnauthorized();
        stripeDelivery($secret, at: time() - 600)->assertUnauthorized();
        stripeDelivery($secret, at: time() + 600)->assertUnauthorized();
        stripeDelivery($secret, header: 'garbage')->assertUnauthorized();
        stripeDelivery($secret, header: 't=abc,v1=def')->assertUnauthorized();
        test()->postJson('/api/hooks/apps/stripe', stripeEvent())->assertUnauthorized();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('files an event under the endpoint whose secret signed it', function () {
        stripeTrigger(['types' => ['invoice.paid']], ['POST /v1/webhook_endpoints' => fn () => stripeEndpoint('we_A', 'whsec_A')]);
        stripeTrigger(['types' => ['customer.created']], ['POST /v1/webhook_endpoints' => fn () => stripeEndpoint('we_B', 'whsec_B')]);

        stripeDelivery('whsec_B', 'evt_5')->assertOk();

        expect(ProviderEvent::query()->where('provider', 'stripe')->sole())
            ->event_id->toBe('evt_5')
            ->routing_key->toBe('we_B')
            ->event_type->toBe('invoice.paid');
    });

    it('is closed while the switch is off', function () {
        stripeTrigger();
        $secret = (string) ProviderWatch::query()->sole()->secret;
        config(['triggers.push.stripe.enabled' => false]);

        stripeDelivery($secret)->assertNotFound();
    });

    it('keeps polling and says why when Stripe refuses the endpoint', function () {
        $refused = stripeTrigger(['types' => ['invoice.paid']], ['POST /v1/webhook_endpoints' => fn () => Http::response(['error' => ['message' => 'Invalid URL: must be https']], 400)]);

        expect($refused->subscription->status)->toBe(SubscriptionStatus::Failed)
            ->and($refused->subscription->last_error)->toContain('400')
            ->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('refuses an endpoint Stripe made without a secret rather than watching what it can\'t verify', function () {
        $trigger = stripeTrigger(['types' => ['invoice.paid']], ['POST /v1/webhook_endpoints' => fn () => ['id' => 'we_1', 'status' => 'enabled']]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed);
    });

    it('turns a disabled endpoint back on, makes it again if it is gone, and removes it', function () {
        stripeTrigger(['types' => ['invoice.paid']]);
        $watch = ProviderWatch::query()->sole();
        $source = app(StripeSource::class);

        fakeStripe(['GET /v1/webhook_endpoints/we_1' => stripeEndpoint(extra: ['status' => 'disabled']), 'POST /v1/webhook_endpoints/we_1' => stripeEndpoint()]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect(stripeSent('POST /v1/webhook_endpoints/we_1'))->toBe(['disabled' => 'false'])
            ->and($watch->fresh()->secret)->toBe('whsec_test_secret');

        fakeStripe([
            'GET /v1/webhook_endpoints/we_1' => fn () => Http::response(['error' => ['message' => 'No such webhook endpoint']], 404),
            'POST /v1/webhook_endpoints' => fn () => stripeEndpoint('we_2', 'whsec_second'),
        ]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh())->status->toBe(SubscriptionStatus::Active)->external_id->toBe('we_2')->secret->toBe('whsec_second')
            ->and(stripeSent('POST /v1/webhook_endpoints')['enabled_events'])->toBe(['invoice.paid']);

        fakeStripe(['DELETE /v1/webhook_endpoints/we_2' => ['id' => 'we_2', 'deleted' => true]]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});

describe('stripe connection', function () {
    it('refuses a key that isn\'t a secret key without calling Stripe, and reads the account for one that is', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $connector = Connector::query()->firstWhere('key', 'stripe') ?? Connector::factory()->create(['key' => 'stripe', 'name' => 'Stripe']);
        $credential = fn (string $key): ConnectorCredential => ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create(['data' => ['api_key' => $key], 'created_by' => $owner->id]);

        fakeStripe([]);
        $publishable = app(ConnectorCredentialTester::class)->test($credential('pk_test_123'));
        expect($publishable['ok'])->toBeFalse()->and($publishable['message'])->toContain('sk_');
        Http::assertNothingSent();

        fakeStripe(['GET /v1/account' => ['id' => 'acct_1', 'business_profile' => ['name' => 'Acme Inc']]]);
        $good = app(ConnectorCredentialTester::class)->test($credential('sk_test_123'));
        expect($good['ok'])->toBeTrue()->and($good['account'])->toBe('Acme Inc (test mode)');

        fakeStripe(['GET /v1/account' => fn () => Http::response(['error' => ['message' => 'Invalid API Key provided']], 401)]);
        expect(app(ConnectorCredentialTester::class)->test($credential('sk_live_bad'))['ok'])->toBeFalse();
    });
});
