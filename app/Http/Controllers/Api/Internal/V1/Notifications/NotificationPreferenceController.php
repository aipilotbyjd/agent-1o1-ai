<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Notifications\NotificationPreference;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $preferences = NotificationPreference::query()
            ->whereBelongsTo($workspace)
            ->whereBelongsTo($request->user())
            ->get();

        return ApiResponse::success(['preferences' => $preferences]);
    }

    public function upsert(Request $request, Workspace $workspace): JsonResponse
    {
        $data = $request->validate([
            'event_key' => ['required', Rule::enum(NotificationEvent::class)],
            'in_app' => ['nullable', 'boolean'],
            'email' => ['nullable', 'boolean'],
            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => [
                'integer',
                Rule::exists('notification_channels', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        $preference = NotificationPreference::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $request->user()->id,
                'event_key' => $data['event_key'],
            ],
            [
                'in_app' => $data['in_app'] ?? NotificationEvent::DEFAULT_IN_APP,
                'email' => $data['email'] ?? NotificationEvent::DEFAULT_EMAIL,
                'channel_ids' => $data['channel_ids'] ?? null,
            ],
        );

        return ApiResponse::success(['preference' => $preference], 'Notification preference saved.');
    }
}
