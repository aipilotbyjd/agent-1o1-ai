<?php

namespace App\Services\Auth;

use App\Models\Credentials\OAuthConnection;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly TwoFactorAuthService $twoFactor,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, tokens: array}
     */
    public function register(array $data): array
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $this->workspaces->create($user, ['name' => "{$user->name}'s Workspace"]);

        $user->sendEmailVerificationNotification();

        return [
            'user' => $user,
            'tokens' => $this->issuePasswordGrantToken($data['email'], $data['password']),
        ];
    }

    /**
     * @return array{user: User, tokens: array}|array{two_factor_challenge: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            return ['two_factor_challenge' => $this->createTwoFactorChallenge($user, $password)];
        }

        return [
            'user' => $user,
            'tokens' => $this->issuePasswordGrantToken($email, $password),
        ];
    }

    /**
     * Completes a 2FA login challenge and issues tokens via the password grant — same
     * shape as a normal login, for frontend parity.
     *
     * The password is held encrypted in the cache alongside the challenge token (5 min TTL,
     * single-use, deleted immediately below) specifically so this step can complete a real
     * password-grant exchange instead of falling back to a refresh-token-less personal
     * access token.
     *
     * @return array{user: User, tokens: array}
     */
    public function completeTwoFactorChallenge(string $challengeToken, string $code): array
    {
        $challenge = Cache::get("2fa-challenge:{$challengeToken}");

        if ($challenge === null) {
            throw ValidationException::withMessages([
                'challenge_token' => 'This two-factor challenge has expired.',
            ]);
        }

        $user = User::query()->findOrFail($challenge['user_id']);

        if (! $this->twoFactor->verifyCode($user, $code)) {
            throw ValidationException::withMessages([
                'code' => 'The provided two-factor code is invalid.',
            ]);
        }

        Cache::forget("2fa-challenge:{$challengeToken}");

        $password = Crypt::decryptString($challenge['password']);

        return [
            'user' => $user,
            'tokens' => $this->issuePasswordGrantToken($user->email, $password),
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->dispatchTokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'scope' => '',
        ]);
    }

    public function logout(User $user): void
    {
        $token = $user->token();

        if ($token) {
            RefreshToken::query()->where('access_token_id', $token->id)->update(['revoked' => true]);
            $token->revoke();
        }
    }

    public function logoutAll(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');

        RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        $user->tokens()->update(['revoked' => true]);
    }

    public function forgotPassword(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    /**
     * @param  array{email: string, password: string, token: string}  $data
     */
    public function resetPassword(array $data): void
    {
        Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password])->save();

            RefreshToken::query()
                ->whereIn('access_token_id', $user->tokens()->pluck('id'))
                ->update(['revoked' => true]);
            $user->tokens()->update(['revoked' => true]);
        });
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword, bool $revokeOtherTokens = false): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->forceFill(['password' => $newPassword])->save();

        if ($revokeOtherTokens) {
            $currentTokenId = $user->token()?->id;

            $otherTokenIds = $user->tokens()
                ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
                ->pluck('id');

            RefreshToken::query()->whereIn('access_token_id', $otherTokenIds)->update(['revoked' => true]);
            $user->tokens()->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))->update(['revoked' => true]);
        }
    }

    /**
     * @return array{user: User, access_token: string}
     */
    public function handleSocialCallback(string $provider, SocialiteUser $socialUser): array
    {
        $connection = OAuthConnection::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($connection) {
            $user = $connection->user;
        } else {
            $user = User::query()->where('email', $socialUser->getEmail())->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail(),
                    'email' => $socialUser->getEmail(),
                    'password' => Str::password(32),
                    'email_verified_at' => now(),
                ]);

                $this->workspaces->create($user, ['name' => "{$user->name}'s Workspace"]);
            }

            OAuthConnection::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
        }

        $token = $user->createToken('social-login-'.$provider);

        return [
            'user' => $user,
            'access_token' => $token->accessToken,
        ];
    }

    /**
     * @return Collection<int, Token>
     */
    public function sessions(User $user)
    {
        return $user->tokens()->where('revoked', false)->orderByDesc('created_at')->get();
    }

    public function revokeSession(User $user, string $tokenId): void
    {
        $token = $user->tokens()->where('id', $tokenId)->firstOrFail();

        RefreshToken::query()->where('access_token_id', $token->id)->update(['revoked' => true]);
        $token->revoke();
    }

    private function createTwoFactorChallenge(User $user, string $password): string
    {
        $challengeToken = Str::random(64);

        Cache::put("2fa-challenge:{$challengeToken}", [
            'user_id' => $user->id,
            'password' => Crypt::encryptString($password),
        ], now()->addMinutes(5));

        return $challengeToken;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    private function issuePasswordGrantToken(string $email, string $password): array
    {
        return $this->dispatchTokenRequest([
            'grant_type' => 'password',
            'client_id' => config('passport.password_client_id'),
            'client_secret' => config('passport.password_client_secret'),
            'username' => $email,
            'password' => $password,
            'scope' => '',
        ]);
    }

    /**
     * Dispatch an in-process request to Passport's own /oauth/token route,
     * avoiding a real network round trip or a live server dependency in tests.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    private function dispatchTokenRequest(array $parameters): array
    {
        $request = Request::create('/oauth/token', 'POST', $parameters);

        $response = app(Kernel::class)->handle($request);

        $payload = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 200) {
            throw ValidationException::withMessages([
                'email' => $payload['error_description'] ?? 'These credentials do not match our records.',
            ]);
        }

        return $payload;
    }
}
