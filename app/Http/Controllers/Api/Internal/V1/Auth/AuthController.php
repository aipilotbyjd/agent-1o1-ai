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
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function register(RegisterRequest $request)
    {
        $result = $this->auth->register($request->validated());

        return ApiResponse::created([
            'user' => UserResource::make($result['user']),
            'tokens' => $result['tokens'],
        ], 'Registered successfully.');
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

        return ApiResponse::success([
            'user' => UserResource::make($result['user']),
            'tokens' => $result['tokens'],
        ], 'Logged in successfully.');
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request)
    {
        $result = $this->auth->completeTwoFactorChallenge(
            $request->validated('challenge_token'),
            $request->validated('code'),
        );

        return ApiResponse::success([
            'user' => UserResource::make($result['user']),
            'tokens' => $result['tokens'],
        ], 'Logged in successfully.');
    }

    public function refresh(Request $request)
    {
        $request->validate(['refresh_token' => ['required', 'string']]);

        $tokens = $this->auth->refresh($request->string('refresh_token')->toString());

        return ApiResponse::success(['tokens' => $tokens]);
    }

    public function logout(Request $request)
    {
        $this->auth->logout($request->user());

        return ApiResponse::noContent();
    }

    public function logoutAll(Request $request)
    {
        $this->auth->logoutAll($request->user());

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
        $socialUser = Socialite::driver($provider)->stateless()->user();

        $result = $this->auth->handleSocialCallback($provider, $socialUser);

        return ApiResponse::success([
            'user' => UserResource::make($result['user']),
            'access_token' => $result['access_token'],
        ], 'Logged in successfully.');
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
}
