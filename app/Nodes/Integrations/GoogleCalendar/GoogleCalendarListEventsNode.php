<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class GoogleCalendarListEventsNode extends AbstractGoogleCalendarNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_calendar_list_events';
    }

    public function name(): string
    {
        return 'Google Calendar: List Events';
    }

    public function description(): string
    {
        return 'Lists upcoming events on a calendar.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'calendar_id' => Field::dynamic('Calendar', 'google_calendar.calendars', 'Defaults to your primary calendar.', 'primary'),
                'time_min' => Field::datetime('From', 'Only events ending after this time. Defaults to now.'),
                'max_results' => Field::integer('Max results', null, 10, 1, 2500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $calendarId = $config['calendar_id'] ?? 'primary';

        return $this->get($run, '/calendars/'.rawurlencode($calendarId).'/events', $config, [
            'timeMin' => $config['time_min'] ?? now()->toRfc3339String(),
            'maxResults' => $config['max_results'] ?? 10,
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
        ]);
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'items';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Google Calendar: New event';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a new event is added to the calendar.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => max((int) ($config['max_results'] ?? 0), 100)];
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
