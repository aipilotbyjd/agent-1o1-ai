<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Models\Runs\Run;

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

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['summary', 'start_at', 'end_at'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'calendar_id' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'start_at' => ['type' => 'string'],
                'end_at' => ['type' => 'string'],
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
