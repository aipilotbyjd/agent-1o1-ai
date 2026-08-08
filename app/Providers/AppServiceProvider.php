<?php

namespace App\Providers;

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Permission;
use App\Models\User;
use App\Models\Workspaces\WorkspaceMember;
use App\Observers\WorkspaceMemberObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }

    private function configurePassport(): void
    {
        Passport::enablePasswordGrant();

        Passport::tokensExpireIn(now()->addMinutes(60));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
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
        RateLimiter::for('auth', fn ($request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('public-api', function ($request) {
            $key = $request->attributes->get('api_key');

            return Limit::perMinute(60)->by($key?->id ?? $request->ip());
        });
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
    }
}
