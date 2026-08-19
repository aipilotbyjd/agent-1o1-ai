<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker
    |--------------------------------------------------------------------------
    |
    | Consecutive event failures (see Trigger::recordFailure()) before a
    | trigger is automatically disabled. A success resets the streak.
    */

    'failures_before_disable' => (int) env('TRIGGERS_FAILURES_BEFORE_DISABLE', 5),

    /*
    |--------------------------------------------------------------------------
    | Webhook intake
    |--------------------------------------------------------------------------
    */

    'hook_rate_limit_per_minute' => (int) env('TRIGGERS_HOOK_RATE_LIMIT_PER_MINUTE', 120),

    // Request headers allow-listed into trigger_events.headers — everything
    // else is dropped before it's ever persisted.
    'allowed_headers' => [
        'X-GitHub-Delivery',
        'X-GitHub-Event',
        'X-Hub-Signature-256',
        'Stripe-Signature',
        'X-Slack-Signature',
        'X-Slack-Request-Timestamp',
        'Content-Type',
        'User-Agent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    */

    'poll_every_minutes' => (int) env('TRIGGERS_POLL_EVERY_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | FireTriggerEvent job
    |--------------------------------------------------------------------------
    */

    // Split so a burst of bulk/periodic polling can't delay a latency-sensitive
    // webhook-fired event sitting behind it on the same worker.
    'fire_queue' => env('TRIGGERS_FIRE_QUEUE', 'triggers-event'),
    'poll_queue' => env('TRIGGERS_POLL_QUEUE', 'triggers-poll'),
    'fire_max_exceptions' => (int) env('TRIGGERS_FIRE_MAX_EXCEPTIONS', 5),
    'fire_timeout_seconds' => (int) env('TRIGGERS_FIRE_TIMEOUT_SECONDS', 120),
    'fire_backoff_seconds' => [10, 30, 90, 300, 900],

    // Agent-targeted events fire on the shared `ai-agent` queue instead: the
    // job blocks on a model call, so it needs both that supervisor's
    // concurrency ceiling (provider rate limits) and a timeout measured in
    // minutes rather than the event queue's seconds. `queue.retry_after` must
    // stay above this value or a still-running turn gets re-dispatched.
    'agent_fire_queue' => env('TRIGGERS_AGENT_FIRE_QUEUE', 'ai-agent'),
    'agent_fire_timeout_seconds' => (int) env('TRIGGERS_AGENT_FIRE_TIMEOUT_SECONDS', 320),

    /*
    |--------------------------------------------------------------------------
    | Stuck-event recovery (triggers:retry-stuck)
    |--------------------------------------------------------------------------
    |
    | Grace periods must comfortably exceed normal processing time, or the
    | retry-stuck command races live work.
    */

    'stuck' => [
        'queued_after_minutes' => (int) env('TRIGGERS_STUCK_QUEUED_AFTER_MINUTES', 10),
        'running_after_minutes' => (int) env('TRIGGERS_STUCK_RUNNING_AFTER_MINUTES', 15),
        'batch_size' => (int) env('TRIGGERS_STUCK_BATCH_SIZE', 100),
    ],

];
