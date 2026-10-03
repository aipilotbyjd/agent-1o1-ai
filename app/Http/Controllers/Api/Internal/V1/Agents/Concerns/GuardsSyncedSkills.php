<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents\Concerns;

use App\Models\Agents\Skill;

/**
 * A skill synced from GitHub is changed in its repository, not here — the
 * next sync would undo the change anyway.
 */
trait GuardsSyncedSkills
{
    protected function ensureNotSynced(Skill $skill): void
    {
        abort_if($skill->isSynced(), 422, 'This skill is synced from GitHub. Change it in the repository, or disconnect the repository to edit it here.');
    }
}
