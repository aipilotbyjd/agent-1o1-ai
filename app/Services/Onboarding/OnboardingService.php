<?php

namespace App\Services\Onboarding;

use App\Enums\Onboarding\DiscoverySource;
use App\Enums\Onboarding\JobRole;
use App\Enums\Onboarding\OnboardingStep;
use App\Enums\Workspaces\Role;
use App\Http\Resources\Api\Internal\V1\Billing\PlanResource;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Services\Workspaces\WorkspaceInvitationService;
use App\Services\Workspaces\WorkspaceService;

class OnboardingService
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly WorkspaceInvitationService $invitations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function state(User $user): array
    {
        $currentStep = $user->onboarding_current_step ?? OnboardingStep::ProfilePicture;

        $steps = collect(OnboardingStep::cases())->map(fn (OnboardingStep $step) => [
            'key' => $step->value,
            'label' => $step->label(),
            'description' => $step->description(),
            'completed' => $this->isStepCompleted($user, $step, $currentStep),
        ]);

        $percent = (int) round($steps->where('completed', true)->count() / $steps->count() * 100);

        return [
            'dismissed' => $user->onboarding_dismissed_at !== null,
            'completed' => $user->hasCompletedOnboarding(),
            'percent' => $percent,
            'current_step' => $currentStep->value,
            'steps' => $steps->all(),
            'meta' => $this->meta($user),
        ];
    }

    /**
     * @param  array<int, string>  $emails
     */
    public function inviteTeam(User $user, array $emails, Role $role, ?string $note): array
    {
        $workspace = $user->currentWorkspace;

        abort_if($workspace === null, 422, 'Create a workspace before inviting your team.');

        foreach ($emails as $email) {
            $this->invitations->invite($workspace, $email, $role, $user);
        }

        $this->advancePast($user, OnboardingStep::InviteTeam);

        return $this->state($user->fresh());
    }

    public function selectRole(User $user, JobRole $role): array
    {
        $user->update(['job_role' => $role]);

        $this->advancePast($user, OnboardingStep::RoleSelection);

        return $this->state($user->fresh());
    }

    public function selectPlan(User $user, string $planSlug): array
    {
        Plan::query()->where('slug', $planSlug)->where('is_active', true)->firstOrFail();

        // Free is the only plan actionable here; paid plans go through Stripe checkout separately.
        $this->advancePast($user, OnboardingStep::ChoosePlan);

        return $this->state($user->fresh());
    }

    public function submitDiscovery(User $user, DiscoverySource $source): array
    {
        $user->update(['discovery_source' => $source]);

        $this->advancePast($user, OnboardingStep::DiscoverySurvey);

        return $this->state($user->fresh());
    }

    public function complete(User $user): array
    {
        $user->update(['onboarding_completed_at' => now()]);

        return $this->state($user->fresh());
    }

    public function dismiss(User $user): void
    {
        $user->update(['onboarding_dismissed_at' => now()]);
    }

    private function isStepCompleted(User $user, OnboardingStep $step, OnboardingStep $currentStep): bool
    {
        if ($user->hasCompletedOnboarding()) {
            return true;
        }

        return match ($step) {
            OnboardingStep::ProfilePicture => $user->avatar !== null,
            OnboardingStep::CreateWorkspace => $user->current_workspace_id !== null,
            OnboardingStep::RoleSelection => $user->job_role !== null,
            OnboardingStep::DiscoverySurvey => $user->discovery_source !== null,
            default => $this->ordinal($step) < $this->ordinal($currentStep),
        };
    }

    private function advancePast(User $user, OnboardingStep $step): void
    {
        $current = $user->onboarding_current_step ?? OnboardingStep::ProfilePicture;

        if ($this->ordinal($step) < $this->ordinal($current)) {
            return;
        }

        $user->update(['onboarding_current_step' => $step->next() ?? $step]);
    }

    private function ordinal(OnboardingStep $step): int
    {
        return array_search($step, OnboardingStep::cases(), strict: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(User $user): array
    {
        return [
            'workspace_slug_suggestion' => $this->workspaces->suggestSlug($user->name),
            'plans' => PlanResource::collection(Plan::query()->where('is_active', true)->orderBy('sort_order')->get()),
            'job_roles' => collect(JobRole::cases())->map(fn (JobRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
            ])->all(),
            'discovery_sources' => collect(DiscoverySource::cases())->map(fn (DiscoverySource $source) => [
                'value' => $source->value,
                'label' => $source->label(),
            ])->all(),
            'credential_types' => [],
        ];
    }
}
