<?php

use App\Models\Runs\Run;
use App\Notifications\Workspace\RunFailedNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notifications store their text when sent, so failed-run notices sent before
 * `RunFailedNotification` named what failed still read "Run <uuid> failed".
 * This rewrites them with the current wording.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('type', RunFailedNotification::class)
            ->orderBy('id')
            ->chunk(200, function ($notifications): void {
                foreach ($notifications as $notification) {
                    $payload = json_decode($notification->data, true);

                    if (! is_array($payload)) {
                        continue;
                    }

                    $runId = $payload['data']['run_id'] ?? null;
                    $run = $runId !== null ? Run::query()->find($runId) : null;

                    $payload['title'] = $run !== null ? RunFailedNotification::titleFor($run) : 'A run failed';
                    $payload['body'] = RunFailedNotification::bodyFor($run?->error ?? $payload['data']['error'] ?? null);

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update(['data' => json_encode($payload)]);
                }
            });
    }

    public function down(): void
    {
        // The original text isn't kept, and the new wording is still accurate.
    }
};
