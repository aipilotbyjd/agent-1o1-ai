<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\LoginRequest;
use App\Services\Admin\AdminAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class LoginController extends Controller
{
    public function __construct(private readonly AdminAuthenticator $authenticator) {}

    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $admin = $this->authenticator->attempt($request, $request->string('email')->toString(), $request->string('password')->toString());
        } catch (TooManyRequestsHttpException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        if ($admin === null) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        return $admin->hasTwoFactorEnabled()
            ? redirect()->route('panel.two-factor.challenge')
            : redirect()->route('panel.two-factor.setup');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authenticator->logout($request);

        return redirect()->route('panel.login')->with('status', 'You have been signed out.');
    }
}
