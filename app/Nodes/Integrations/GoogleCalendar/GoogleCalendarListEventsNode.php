<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Models\Runs\Run;

class GoogleCalendarListEventsNode extends AbstractGoogleCalendarNode
{
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

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'calendar_id' => ['type' => 'string'],
                'time_min' => ['type' => 'string'],
                'max_results' => ['type' => 'integer'],
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
}
