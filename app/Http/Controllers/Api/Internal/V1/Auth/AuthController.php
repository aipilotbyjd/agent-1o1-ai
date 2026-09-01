<?php

namespace App\Http\Controllers\Api\Internal\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Internal\V1\Auth\LoginRequest;
use App\Http\Requests\Api\Internal\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\Internal\V1\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\Api\Internal\V1\Auth\TokenResource;
use App\Http\Resources\Api\Internal\V1\User\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const REFRESH_COOKIE_MINUTES = 60 * 24 * 30;

    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function register(RegisterRequest $request)
    {
        $result = $this->auth->register($request->validated());

        return $this->respondWithTokens($result['user'], $result['tokens'], 'Registered successfully.', 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login($request->validated('email'), $request->validated('password'));

        if (isset($result['two_factor_challenge'])) {
            return ApiResponse::success(
                ['two_factor_challenge' => $result['two_factor_challenge']],
                'Two-factor authentication code required.',
            );
        }

        return $this->respondWithTokens($result['user'], $result['tokens'], 'Logged in successfully.');
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request)
    {
        $result = $this->auth->completeTwoFactorChallenge(
            $request->validated('challenge_token'),
            $request->validated('code'),
        );

        return $this->respondWithTokens($result['user'], $result['tokens'], 'Logged in successfully.');
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie(self::REFRESH_COOKIE);

        abort_if($refreshToken === null, 401, 'Missing refresh token.');

        $tokens = $this->auth->refresh($refreshToken);

        Cookie::queue($this->makeRefreshCookie($tokens['refresh_token']));
        unset($tokens['refresh_token']);

        return ApiResponse::success(['tokens' => $tokens]);
    }

    public function logout(Request $request)
    {
        $this->auth->logout($request->user());
        Cookie::queue(Cookie::forget(self::REFRESH_COOKIE));

        return ApiResponse::noContent();
    }

    public function logoutAll(Request $request)
    {
        $this->auth->logoutAll($request->user());
        Cookie::queue(Cookie::forget(self::REFRESH_COOKIE));

        return ApiResponse::noContent();
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $this->auth->forgotPassword($request->string('email')->toString());

        return ApiResponse::success(message: 'If that email exists, a reset link has been sent.');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed'],
        ]);

        $this->auth->resetPassword($request->only('token', 'email', 'password', 'password_confirmation'));

        return ApiResponse::success(message: 'Password reset successfully.');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $this->auth->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            (bool) $request->boolean('revoke_other_sessions'),
        );

        return ApiResponse::noContent();
    }

    public function verifyEmail(Request $request, int $id)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired verification link.');
        }

        $user = User::query()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403, 'Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return ApiResponse::success(message: 'Email verified.');
    }

    public function resendVerification(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return ApiResponse::success(message: 'Verification email sent.');
    }

    public function redirectToProvider(string $provider)
    {
        return ApiResponse::success([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    public function handleProviderCallback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $result = $this->auth->handleSocialCallback($provider, $socialUser);
        } catch (\Throwable $e) {
            report($e);

            return redirect(config('app.frontend_url').'/oauth/callback?error='.urlencode('Sign-in failed. Please try again.'));
        }

        $code = Str::random(40);

        Cache::put("oauth-exchange:{$code}", [
            'user_id' => $result['user']->id,
            'tokens' => $result['tokens'],
        ], now()->addSeconds(60));

        return redirect(config('app.frontend_url')."/oauth/callback?code={$code}");
    }

    public function exchangeSocialCode(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $result = $this->auth->exchangeSocialCode($request->string('code')->toString());

        return $this->respondWithTokens($result['user'], $result['tokens'], 'Logged in successfully.');
    }

    public function sessions(Request $request)
    {
        return ApiResponse::success(['sessions' => TokenResource::collection($this->auth->sessions($request->user()))]);
    }

    public function revokeSession(Request $request, string $tokenId)
    {
        $this->auth->revokeSession($request->user(), $tokenId);

        return ApiResponse::noContent();
    }

    private function respondWithTokens(User $user, array $tokens, string $message, int $status = 200)
    {
        Cookie::queue($this->makeRefreshCookie($tokens['refresh_token']));
        unset($tokens['refresh_token']);

        return ApiResponse::success([
            'user' => UserResource::make($user),
            'tokens' => $tokens,
        ], $message, $status);
    }

    private function makeRefreshCookie(string $refreshToken)
    {
        return Cookie::make(
            self::REFRESH_COOKIE,
            $refreshToken,
            self::REFRESH_COOKIE_MINUTES,
            '/',
            null,
            true,
            true,
            false,
            'none',
        );
    }
}
