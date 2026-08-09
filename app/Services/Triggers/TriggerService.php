<?php

namespace App\Services\Triggers;

use App\Contracts\Triggers\RunStarter;
use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * See docs/TRIGGERS_PLAN.md's "Core pipeline" diagram — `receive()` is the fast
 * intake path (a few indexed queries and one INSERT, never a model call, never
 * a graph walk), `fire()` is the slow path a queued job runs after the event
 * row already exists.
 */
class TriggerService
{
    public function __construct(private readonly RunStarter $runStarter) {}

    /**
     * @param  array{preset_id?: int, config?: array<string, mixed>, is_active?: bool, credential_id?: int, signing_secret?: string}  $data
     */
    public function create(
        Workspace $workspace,
        TriggerTargetType $targetType,
        int $targetId,
        TriggerType $type,
        array $data,
        ?User $createdBy = null,
    ): Trigger {
        $trigger = Trigger::query()->create([
            'workspace_id' => $workspace->id,
            'target_type' => $targetType->value,
            'target_id' => $targetId,
            'type' => $type,
            'preset_id' => $data['preset_id'] ?? null,
            'config' => $data['config'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'credential_id' => $data['credential_id'] ?? null,
            'created_by' => $createdBy?->id,
        ]);

        if ($type->usesToken()) {
            $trigger->forceFill(['token' => Trigger::generateToken()])->save();
        }

        if (isset($data['signing_secret'])) {
            $trigger->forceFill(['signing_secret' => $data['signing_secret']])->save();
        }

        return $trigger;
    }

    /**
     * @param  array{config?: array<string, mixed>, is_active?: bool}  $data
     */
    public function update(Trigger $trigger, array $data): Trigger
    {
        $trigger->update([
            'config' => $data['config'] ?? $trigger->config,
            'is_active' => $data['is_active'] ?? $trigger->is_active,
        ]);

        return $trigger;
    }

    public function rotateToken(Trigger $trigger): Trigger
    {
        abort_unless($trigger->type->usesToken(), 422, 'Only webhook triggers have a token to rotate.');

        $trigger->forceFill(['token' => Trigger::generateToken()])->save();

        return $trigger;
    }

    /**
     * The fast intake path for automated sources (webhook/schedule/polling).
     * The manual path has its own synchronous pre-checks in the controller
     * (isAlreadyRunning/canRun return an immediate 409) before calling this.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function receive(
        Trigger $trigger,
        TriggerType $source,
        array $payload,
        ?string $deliveryId = null,
        ?string $payloadSnippet = null,
        array $headers = [],
    ): TriggerEvent {
        if (! $trigger->is_active) {
            return $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Skipped, 'Trigger is inactive.');
        }

        if ($deliveryId !== null) {
            $duplicate = $this->duplicateOf($trigger, $deliveryId);

            if ($duplicate !== null) {
                return $duplicate;
            }
        }

        if (! $this->matchesConfiguredFilters($trigger, $payload, $headers)) {
            return $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Ignored);
        }

        if (! $this->runStarter->canRun($trigger)) {
            return $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Skipped, 'Target is not runnable.');
        }

        try {
            $event = TriggerEvent::query()->create([
                'trigger_id' => $trigger->id,
                'source' => $source,
                'status' => TriggerEventStatus::Queued,
                'payload' => $payload,
                'payload_snippet' => $payloadSnippet,
                'headers' => $this->allowedHeaders($headers),
                'delivery_id' => $deliveryId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost the race to a concurrent delivery of the same event — the
            // unique index is the dedupe guarantee, not the earlier read-check.
            return $this->duplicateOf($trigger, $deliveryId) ?? throw new UniqueConstraintViolationException;
        }

        // The insert above is its own auto-committed statement — nothing in
        // this call path wraps receive() in an explicit transaction — so the
        // row already exists by the time this dispatches. If a future caller
        // ever wraps receive() in DB::transaction(), add ->afterCommit() here
        // to keep that guarantee; the row-before-job rule is why.
        FireTriggerEvent::dispatch($trigger, $event);

        return $event;
    }

    /**
     * Rejected deliveries are stored WITHOUT a delivery id — otherwise an
     * attacker blocks a legitimate delivery by guessing its id and sending a
     * badly signed request first.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function reject(
        Trigger $trigger,
        TriggerType $source,
        array $payload,
        ?string $payloadSnippet,
        array $headers,
        string $reason,
    ): TriggerEvent {
        return TriggerEvent::query()->create([
            'trigger_id' => $trigger->id,
            'source' => $source,
            'status' => TriggerEventStatus::Rejected,
            'payload' => $payload,
            'payload_snippet' => $payloadSnippet,
            'headers' => $this->allowedHeaders($headers),
            'delivery_id' => null,
            'error' => $reason,
            'processed_at' => now(),
        ]);
    }

    /**
     * The slow path: actually start the target, then record the outcome.
     * Any exception here is left to propagate — `FireTriggerEvent`'s own
     * retry/backoff and `failed()` circuit-breaker handling owns what happens
     * next, not this method.
     */
    public function fire(Trigger $trigger, TriggerEvent $event): void
    {
        $runId = $this->runStarter->start($trigger, $event->payload ?? []);

        $event->markFired($runId);
        $trigger->recordSuccess();
    }

    public function recordFailure(Trigger $trigger): void
    {
        $trigger->recordFailure();
    }

    public function canRun(Trigger $trigger): bool
    {
        return $trigger->is_active && $this->runStarter->canRun($trigger);
    }

    /**
     * Checked only on the manual-run path — a person double-clicking wants to
     * be told "busy"; a provider redelivering wants its event queued
     * regardless. See docs/TRIGGERS_PLAN.md's design decisions table.
     */
    public function isAlreadyRunning(Trigger $trigger): bool
    {
        return $this->runStarter->isAlreadyRunning($trigger);
    }

    private function duplicateOf(Trigger $trigger, string $deliveryId): ?TriggerEvent
    {
        $existing = TriggerEvent::query()
            ->where('trigger_id', $trigger->id)
            ->where('delivery_id', $deliveryId)
            ->first();

        if ($existing === null) {
            return null;
        }

        $existing->increment('duplicate_count');

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function matchesConfiguredFilters(Trigger $trigger, array $payload, array $headers): bool
    {
        $filters = $trigger->config['filters'] ?? [];

        foreach ($filters as $filter) {
            $subject = ($filter['source'] ?? 'payload') === 'header' ? $headers : $payload;
            $value = data_get($subject, $filter['path'] ?? '');

            $matches = match ($filter['operator'] ?? 'equals') {
                'equals' => $value == ($filter['value'] ?? null),
                'not_equals' => $value != ($filter['value'] ?? null),
                'contains' => is_string($value) && str_contains($value, (string) ($filter['value'] ?? '')),
                default => true,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function allowedHeaders(array $headers): array
    {
        $allowList = array_map('strtolower', (array) config('triggers.allowed_headers', []));

        return array_filter(
            $headers,
            fn (string $key): bool => in_array(strtolower($key), $allowList, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Write an event that has already reached a terminal, non-`Rejected`
     * outcome (inactive/filtered/unrunnable) — still dedupes by delivery id,
     * unlike `reject()`.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function writeTerminal(
        Trigger $trigger,
        TriggerType $source,
        array $payload,
        ?string $deliveryId,
        ?string $payloadSnippet,
        array $headers,
        TriggerEventStatus $status,
        ?string $error = null,
    ): TriggerEvent {
        try {
            return TriggerEvent::query()->create([
                'trigger_id' => $trigger->id,
                'source' => $source,
                'status' => $status,
                'payload' => $payload,
                'payload_snippet' => $payloadSnippet,
                'headers' => $this->allowedHeaders($headers),
                'delivery_id' => $deliveryId,
                'error' => $error,
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateOf($trigger, (string) $deliveryId) ?? throw new UniqueConstraintViolationException;
        }
    }
}
