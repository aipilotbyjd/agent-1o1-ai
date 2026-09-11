<?php

namespace App\Providers;

use App\Authorization\WorkspaceContext;
use App\Contracts\Triggers\RunStarter;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Workspaces\Permission;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Agents\ReflectionRun;
use App\Models\Auth\PassportToken;
use App\Models\Billing\Subscription as BillingSubscription;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Observers\AgentMessageObserver;
use App\Observers\AgentObserver;
use App\Observers\NodeRunObserver;
use App\Observers\RunObserver;
use App\Observers\WorkspaceMemberObserver;
use App\Services\Auth\Grants\SocialExchangeGrant;
use App\Services\Triggers\TargetRunStarter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RunStarter::class, TargetRunStarter::class);

        // Must run in register(), not boot(): CashierServiceProvider's own
        // boot() (which registers its default routes unless this flag is
        // already set) runs before this provider's boot() does.
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();
        $this->configureAuthNotificationUrls();
        $this->configurePasswordDefaults();
        $this->configureRateLimiting();
        $this->configureGate();
        $this->configureObservers();
        $this->configureMorphMap();
        $this->configureCashier();
    }

    private function configurePassport(): void
    {
        Passport::enablePasswordGrant();
        Passport::useTokenModel(PassportToken::class);

        Passport::tokensExpireIn(now()->addMinutes(60));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        $this->enableSocialExchangeGrant();
    }

    /**
     * Registers the custom grant that turns a completed social sign-in into a
     * token pair (see `SocialExchangeGrant`).
     *
     * `enableGrantType()` wires the access token, client and scope repositories
     * but not the refresh token one, so that is injected here — without it the
     * grant would issue an access token with no way to renew it, which is the
     * whole reason the old implementation resorted to rotating passwords.
     */
    private function enableSocialExchangeGrant(): void
    {
        $this->app->resolving(AuthorizationServer::class, function (AuthorizationServer $server): void {
            $grant = new SocialExchangeGrant;
            $grant->setRefreshTokenRepository($this->app->make(RefreshTokenRepository::class));
            $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());

            $server->enableGrantType($grant, Passport::tokensExpireIn());
        });
    }

    private function configureAuthNotificationUrls(): void
    {
        VerifyEmail::createUrlUsing(function (User $user) {
            return URL::temporarySignedRoute(
                'auth.verify-email',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );
        });

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }

    private function configurePasswordDefaults(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());
    }

    private function configureRateLimiting(): void
    {
        // Two limits, not one: the IP limit caps how fast a single source can
        // work, and the email limit caps how fast one account can be worked on
        // no matter how many sources are spread across it. `LoginThrottle`
        // then locks the account outright once failures accumulate.
        RateLimiter::for('auth', fn (Request $request): array => array_filter([
            Limit::perMinute(10)->by($request->ip()),
            $this->authEmailLimit($request),
        ]));

        RateLimiter::for('public-api', function ($request) {
            $key = $request->attributes->get('api_key');

            return Limit::perMinute(60)->by($key?->id ?? $request->ip());
        });

        // Keyed by token rather than IP: one noisy provider must not rate-limit
        // every other trigger sharing its egress IPs.
        RateLimiter::for('trigger-hooks', fn (Request $request): Limit => Limit::perMinute(
            (int) config('triggers.hook_rate_limit_per_minute'),
        )->by($request->route('token') ?? $request->ip()));
    }

    /**
     * Keyed on the address being signed in as, so credential stuffing spread
     * across many IPs still collides on the account it is targeting. Returns
     * null for requests that carry no email — password resets and refreshes
     * share this limiter and have nothing to key on.
     */
    private function authEmailLimit(Request $request): ?Limit
    {
        $email = $request->input('email');

        if (! is_string($email) || $email === '') {
            return null;
        }

        return Limit::perMinute(5)->by('auth-email:'.hash('sha256', mb_strtolower($email)));
    }

    private function configureGate(): void
    {
        Gate::before(function (User $user, string $ability) {
            $permission = Permission::tryFrom($ability);

            if ($permission === null) {
                return null;
            }

            if (! app()->bound(WorkspaceContext::class)) {
                return false;
            }

            return app(WorkspaceContext::class)->allows($permission) ?: null;
        });
    }

    private function configureObservers(): void
    {
        WorkspaceMember::observe(WorkspaceMemberObserver::class);

        // Behavioral history for agents — see `AgentVersioner`.
        Agent::observe(AgentObserver::class);

        // Live run/chat streaming — see `App\Broadcasting\Channels`.
        Run::observe(RunObserver::class);
        NodeRun::observe(NodeRunObserver::class);
        AgentMessage::observe(AgentMessageObserver::class);
    }

    /**
     * Short aliases instead of fully-qualified class names, shared by
     * `triggers.target_type` (`Workflow`/`Agent`) and `runs.runnable_type`
     * (`Workflow`/`AgentSession` — a run's target and a trigger's target
     * happen to overlap on `Workflow`, and both need every runnable/
     * targetable model registered once `enforceMorphMap()` is on, or any
     * relation touching an unregistered one throws).
     *
     * `workflow_template`/`agent_template` back `TemplateCollectionItem`'s
     * `templatable` morph — the alias, not the FQCN, is what's stored in
     * `template_collection_items.templatable_type`.
     */
    private function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            TriggerTargetType::Workflow->value => Workflow::class,
            TriggerTargetType::Agent->value => Agent::class,
            'agent_session' => AgentSession::class,
            'agent_eval_run' => AgentEvalRun::class,
            'reflection_run' => ReflectionRun::class,
            'agent_session_evaluation' => AgentSessionEvaluation::class,
            'workflow_template' => WorkflowTemplate::class,
            'agent_template' => AgentTemplate::class,
            // Needed for Laravel\Ai\Concerns\RemembersConversations'
            // forUser()/HasConversations participant polymorphism
            // (WorkflowBuilderAgent) — the conversations table's
            // participant_type column stores this alias.
            'user' => User::class,
        ]);
    }

    private function configureCashier(): void
    {
        Cashier::useCustomerModel(Workspace::class);
        Cashier::useSubscriptionModel(BillingSubscription::class);
    }
}
