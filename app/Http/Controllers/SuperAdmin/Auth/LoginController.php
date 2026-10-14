<?php

namespace App\Http\Controllers\SuperAdmin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Auth\LoginRequest;
use App\Services\SuperAdmin\SuperAdminAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class LoginController extends Controller
{
    public function __construct(private readonly SuperAdminAuthenticator $authenticator) {}

    public function create(): View
    {
        return view('super-admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $superAdmin = $this->authenticator->attempt($request, $request->string('email')->toString(), $request->string('password')->toString());
        } catch (TooManyRequestsHttpException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        if ($superAdmin === null) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        return $superAdmin->hasTwoFactorEnabled()
            ? redirect()->route('super-admin.two-factor.challenge')
            : redirect()->route('super-admin.two-factor.setup');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authenticator->logout($request);

        return redirect()->route('super-admin.login')->with('status', 'You have been signed out.');
    }
}
