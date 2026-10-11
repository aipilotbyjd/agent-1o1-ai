<?php

namespace App\Services\Workflows\Pages;

use App\Enums\Billing\Feature;
use App\Enums\Billing\PlanLimit;
use App\Enums\Workflows\PageAccess;
use App\Enums\Workspaces\AuditAction;
use App\Exceptions\FeatureNotAvailableException;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Services\Billing\PlanLimitGate;
use App\Services\Workspaces\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Authoring workflow pages: creating one from a workflow, saving the draft,
 * publishing it, and deciding who can open it.
 *
 * Sharing outside the workspace needs `Feature::PublicPages`, and a page
 * behind a password needs one set. Publishing copies the draft over the live
 * page only when `PageSchema::issues()` finds no errors.
 */
class WorkflowPages
{
    public function __construct(
        private readonly PageSchema $schema,
        private readonly PlanLimitGate $limits,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Workflow $workflow, User $creator, ?string $name = null, ?string $description = null): WorkflowPage
    {
        $this->limits->assertCanCreate($workflow->workspace, PlanLimit::Pages);

        $name = filled($name) ? $name : $workflow->name;

        return $workflow->pages()->create([
            'workspace_id' => $workflow->workspace_id,
            'created_by' => $creator->id,
            'name' => $name,
            'description' => $description,
            'content' => $this->schema->starter($workflow, $name),
        ]);
    }

    public function duplicate(WorkflowPage $page, User $creator): WorkflowPage
    {
        $this->limits->assertCanCreate($page->workspace, PlanLimit::Pages);

        return WorkflowPage::query()->create([
            'workspace_id' => $page->workspace_id,
            'workflow_id' => $page->workflow_id,
            'created_by' => $creator->id,
            'name' => "{$page->name} (copy)",
            'description' => $page->description,
            'content' => $page->content,
        ]);
    }

    /**
     * Applies whichever of the page's editable attributes `$changes` holds.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(WorkflowPage $page, array $changes): WorkflowPage
    {
        $accessBefore = $page->access;

        foreach (['name', 'description', 'is_enabled', 'max_runs_per_day'] as $key) {
            if (array_key_exists($key, $changes)) {
                $page->{$key} = $changes[$key];
            }
        }

        if (array_key_exists('content', $changes)) {
            $page->content = $this->schema->normalize($changes['content']);
        }

        if (array_key_exists('workflow_id', $changes) && $changes['workflow_id'] !== $page->workflow_id) {
            $page->workflow_id = $changes['workflow_id'];
        }

        if (array_key_exists('password', $changes) && filled($changes['password'])) {
            $page->password = $changes['password'];
            // A new password signs everyone out, the same as a new link.
            $page->public_token = WorkflowPage::newToken();
        }

        if (array_key_exists('access', $changes)) {
            $access = PageAccess::from($changes['access']);

            if ($access->isPublic() && ! $page->workspace->currentPlan()?->hasFeature(Feature::PublicPages)) {
                throw new FeatureNotAvailableException(Feature::PublicPages);
            }

            if ($access === PageAccess::Password && $page->password === null) {
                throw ValidationException::withMessages(['password' => 'Set a password for this page first.']);
            }

            $page->access = $access;

            if ($access !== PageAccess::Password) {
                $page->password = null;
            }
        }

        $page->save();

        if ($page->access !== $accessBefore) {
            $this->audit->record($page->workspace_id, AuditAction::PageAccessChanged, $page, [
                'from' => $accessBefore->value,
                'to' => $page->access->value,
            ]);
        }

        return $page;
    }

    public function publish(WorkflowPage $page, User $publisher): WorkflowPage
    {
        $issues = $this->schema->issues($page->content, $page->workflow);

        if (PageSchema::hasErrors($issues)) {
            throw ValidationException::withMessages([
                'content' => collect($issues)->where('severity', 'error')->pluck('message')->values()->all(),
            ]);
        }

        $page->forceFill([
            'published_content' => $page->content,
            'published_at' => now(),
            'published_by' => $publisher->id,
        ])->save();

        $this->audit->record($page->workspace_id, AuditAction::PagePublished, $page, ['name' => $page->name]);

        return $page;
    }

    public function unpublish(WorkflowPage $page): WorkflowPage
    {
        $page->forceFill(['published_content' => null, 'published_at' => null])->save();

        $this->audit->record($page->workspace_id, AuditAction::PageUnpublished, $page, ['name' => $page->name]);

        return $page;
    }

    public function rotateLink(WorkflowPage $page): WorkflowPage
    {
        $page->forceFill(['public_token' => WorkflowPage::newToken()])->save();

        $this->audit->record($page->workspace_id, AuditAction::PageLinkRotated, $page, ['name' => $page->name]);

        return $page;
    }

    public function delete(WorkflowPage $page): void
    {
        DB::transaction(function () use ($page): void {
            $this->audit->record($page->workspace_id, AuditAction::PageDeleted, $page, ['name' => $page->name]);
            $page->delete();
        });
    }
}
