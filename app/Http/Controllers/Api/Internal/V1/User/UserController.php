<?php

namespace App\Http\Controllers\Api\Internal\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\User\SwitchWorkspaceRequest;
use App\Http\Requests\Api\Internal\V1\User\UploadAvatarRequest;
use App\Http\Resources\Api\Internal\V1\User\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
    ) {}

    public function show(Request $request)
    {
        return ApiResponse::success(['user' => UserResource::make($request->user())]);
    }

    public function switchWorkspace(SwitchWorkspaceRequest $request)
    {
        $workspace = Workspace::findOrFail($request->validated('workspace_id'));

        $this->workspaces->switchTo($request->user(), $workspace);

        return ApiResponse::success(['user' => UserResource::make($request->user()->fresh())]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $request->user()->update($data);

        return ApiResponse::success(['user' => UserResource::make($request->user())], 'User updated successfully.');
    }

    public function uploadAvatar(UploadAvatarRequest $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        return ApiResponse::success(['user' => UserResource::make($user)], 'Avatar uploaded successfully.');
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return ApiResponse::success(['user' => UserResource::make($user)], 'Avatar removed successfully.');
    }

    public function destroy(Request $request)
    {
        $request->user()->tokens()->update(['revoked' => true]);
        $request->user()->delete();

        return ApiResponse::noContent();
    }
}
