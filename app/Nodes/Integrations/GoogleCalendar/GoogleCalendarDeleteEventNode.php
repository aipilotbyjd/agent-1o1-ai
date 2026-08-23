<?php

namespace App\Nodes\Integrations\GoogleCalendar;

use App\Models\Runs\Run;

class GoogleCalendarDeleteEventNode extends AbstractGoogleCalendarNode
{
    public function type(): string
    {
        return 'google_calendar_delete_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Delete Event';
    }

    public function description(): string
    {
        return 'Deletes an event from a calendar.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['event_id'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'calendar_id' => ['type' => 'string'],
                'event_id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $calendarId = $config['calendar_id'] ?? 'primary';

        return $this->delete(
            $run,
            '/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($config['event_id']),
            $config,
        );
    }
}
