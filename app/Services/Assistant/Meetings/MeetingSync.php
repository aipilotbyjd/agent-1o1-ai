<?php

namespace App\Services\Assistant\Meetings;

use App\Enums\Assistant\AssistantMeetingPrepStatus;
use App\Models\Assistant\Assistant;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use Illuminate\Support\Str;

/**
 * Copies the owner's next few days of meetings from Google Calendar, so
 * they can be listed and prepped. Events with no other guests are not
 * meetings and are left out; meetings that disappeared from the calendar
 * (cancelled, moved) are dropped unless a brief was already written.
 */
class MeetingSync
{
    private const int MAX_EVENTS = 50;

    public function __construct(
        private readonly NodeReader $reader,
        private readonly ConnectorToolProvider $connectors,
    ) {}

    public function isConnected(Assistant $assistant): bool
    {
        return $this->connectors->credentialsFor($assistant)->has('google_calendar');
    }

    /**
     * @return int how many upcoming meetings are now known
     */
    public function sync(Assistant $assistant): int
    {
        $credential = $this->connectors->credentialsFor($assistant)->get('google_calendar');

        if ($credential === null) {
            return 0;
        }

        $ownerEmail = Str::lower((string) $assistant->user->email);
        $until = now()->addDays((int) config('assistant.meetings.sync_days'));

        $events = collect($this->reader->read('google_calendar_list_events', $assistant, $credential, [
            'time_min' => now()->toRfc3339String(),
            'max_results' => self::MAX_EVENTS,
        ])['items'] ?? [])
            ->filter(fn (array $event): bool => isset($event['start']['dateTime']) && ($event['status'] ?? 'confirmed') !== 'cancelled')
            ->filter(fn (array $event): bool => now()->parse($event['start']['dateTime'])->lte($until));

        $seen = [];

        foreach ($events as $event) {
            $attendees = collect($event['attendees'] ?? [])
                ->reject(fn (array $attendee): bool => ($attendee['self'] ?? false) || ($attendee['resource'] ?? false) || Str::lower($attendee['email'] ?? '') === $ownerEmail)
                ->map(fn (array $attendee): array => ['email' => Str::lower((string) ($attendee['email'] ?? '')), 'name' => $attendee['displayName'] ?? null])
                ->filter(fn (array $attendee): bool => $attendee['email'] !== '')
                ->values()
                ->all();

            if ($attendees === []) {
                continue;
            }

            $startsAt = now()->parse($event['start']['dateTime'])->utc();

            $meeting = $assistant->meetings()->updateOrCreate(
                ['provider_event_id' => (string) $event['id'], 'starts_at' => $startsAt],
                [
                    'title' => Str::limit((string) ($event['summary'] ?? 'Untitled meeting'), 250),
                    'ends_at' => isset($event['end']['dateTime']) ? now()->parse($event['end']['dateTime'])->utc() : null,
                    'attendees' => $attendees,
                    'is_external' => ExternalMeeting::isExternal($ownerEmail, $attendees),
                    'html_link' => $event['htmlLink'] ?? null,
                    'description' => isset($event['description']) ? Str::limit(strip_tags((string) $event['description']), 2000) : null,
                ],
            );

            $seen[] = $meeting->id;
        }

        $assistant->meetings()
            ->where('starts_at', '>', now())
            ->whereNotIn('id', $seen)
            ->where('prep_status', AssistantMeetingPrepStatus::None)
            ->delete();

        return count($seen);
    }
}
