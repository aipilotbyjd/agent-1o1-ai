<?php

namespace App\Services\Workspaces;

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    /**
     * @param  array{name: string, avatar?: string|null}  $data
     */
    public function create(User $owner, array $data): Workspace
    {
        return DB::transaction(function () use ($owner, $data) {
            $workspace = Workspace::query()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'avatar' => $data['avatar'] ?? null,
                'owner_id' => $owner->id,
            ]);

            $workspace->members()->create([
                'user_id' => $owner->id,
                'role' => Role::Owner,
                'joined_at' => now(),
            ]);

            return $workspace;
        });
    }

    /**
     * @param  array{name?: string}  $data
     */
    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        return $workspace;
    }

    public function updateAvatar(Workspace $workspace, string $path): Workspace
    {
        $workspace->update(['avatar' => $path]);

        return $workspace;
    }

    public function delete(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace) {
            $workspace->members()->delete();
            $workspace->invitations()->delete();
            $workspace->delete();
        });
    }

    public function updateMemberRole(Workspace $workspace, User $member, Role $role): void
    {
        $workspaceMember = $workspace->members()->where('user_id', $member->id)->firstOrFail();

        if ($workspaceMember->role === Role::Owner) {
            throw new AuthorizationException("The workspace owner's role cannot be changed.");
        }

        $workspaceMember->update(['role' => $role]);
    }

    public function removeMember(Workspace $workspace, User $member): void
    {
        $workspaceMember = $workspace->members()->where('user_id', $member->id)->firstOrFail();

        if ($workspaceMember->role === Role::Owner) {
            throw new AuthorizationException('The workspace owner cannot be removed.');
        }

        $workspaceMember->delete();
    }

    public function leave(Workspace $workspace, User $user): void
    {
        if ($workspace->owner_id === $user->id) {
            throw new AuthorizationException('Transfer ownership before leaving this workspace.');
        }

        $workspace->members()->where('user_id', $user->id)->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Workspace::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
