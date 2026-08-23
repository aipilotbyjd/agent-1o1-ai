<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarCreateEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarDeleteEventNode;
use App\Nodes\Integrations\GoogleCalendar\GoogleCalendarListEventsNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every google calendar node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'google_calendar_list_events' => GoogleCalendarListEventsNode::class,
        'google_calendar_create_event' => GoogleCalendarCreateEventNode::class,
        'google_calendar_delete_event' => GoogleCalendarDeleteEventNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('google_calendar');
    }
});

it('lists events defaulting to the primary calendar', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['items' => []])]);

    $node = new GoogleCalendarListEventsNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'max_results' => 5], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/calendar/v3/calendars/primary/events')
            && $request['maxResults'] == 5;
    });
});

it('creates an event on the given calendar', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['id' => 'e1'])]);

    $node = new GoogleCalendarCreateEventNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'ya29-test',
        'calendar_id' => 'team@example.com',
        'summary' => 'Standup',
        'start_at' => '2026-01-01T09:00:00Z',
        'end_at' => '2026-01-01T09:15:00Z',
    ], []);

    expect($output)->toBe(['id' => 'e1']);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/calendars/team%40example.com/events')
            && $request['summary'] === 'Standup'
            && $request['start']['dateTime'] === '2026-01-01T09:00:00Z';
    });
});

it('deletes an event by id', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response('', 204)]);

    $node = new GoogleCalendarDeleteEventNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'event_id' => 'e1'], []);

    expect($output)->toBe([]);
    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE' && str_contains($request->url(), '/calendars/primary/events/e1');
    });
});

it('throws when calendar answers a non-2xx status', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['error' => ['message' => 'Not Found']], 404)]);

    $node = new GoogleCalendarDeleteEventNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, ['access_token' => 'ya29-test', 'event_id' => 'missing'], []))
        ->toThrow(RuntimeException::class, 'Not Found');
});

it('throws when access_token is missing', function () {
    $node = new GoogleCalendarListEventsNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [], []))->toThrow(RuntimeException::class, 'access_token');
});
