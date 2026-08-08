<?php

namespace App\Services\Workspaces;

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceInvitationService
{
    public function invite(Workspace $workspace, string $email, Role $role, User $invitedBy): WorkspaceInvitation
    {
        $alreadyMember = $workspace->users()->where('email', $email)->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the workspace.',
            ]);
        }

        return $workspace->invitations()->create([
            'email' => $email,
            'role' => $role,
            'token' => Str::random(40),
            'invited_by' => $invitedBy->id,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function accept(WorkspaceInvitation $invitation, User $user): void
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages(['token' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['token' => 'This invitation has expired.']);
        }

        if ($invitation->email !== $user->email) {
            throw ValidationException::withMessages(['email' => 'This invitation was sent to a different email address.']);
        }

        $existing = $invitation->workspace->members()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->restore();
            $existing->update(['role' => $invitation->role, 'joined_at' => now()]);
        } else {
            $invitation->workspace->members()->create([
                'user_id' => $user->id,
                'role' => $invitation->role,
                'invited_by' => $invitation->invited_by,
                'joined_at' => now(),
            ]);
        }

        $invitation->update(['accepted_at' => now()]);
    }

    public function revoke(WorkspaceInvitation $invitation): void
    {
        $invitation->delete();
    }
}
