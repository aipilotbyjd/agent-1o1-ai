<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleCalendarCreateEventNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_create_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Create Event';
    }

    public function description(): string
    {
        return 'Creates an event on a calendar.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['summary', 'start_at', 'end_at'],
            'properties' => [
                ...$this->credentialFields(),
                'calendar_id' => Field::dynamic('Calendar', 'google_calendar.calendars', 'Defaults to your primary calendar.', 'primary'),
                'summary' => Field::text('Title', null, 'Team sync'),
                'description' => Field::textarea('Description'),
                'start_at' => Field::datetime('Starts at'),
                'end_at' => Field::datetime('Ends at'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $calendarId = $config['calendar_id'] ?? 'primary';

        return $this->post($run, '/calendars/'.rawurlencode($calendarId).'/events', $config, [
            'summary' => $config['summary'],
            'description' => $config['description'] ?? null,
            'start' => ['dateTime' => $config['start_at']],
            'end' => ['dateTime' => $config['end_at']],
        ]);
    }
}
