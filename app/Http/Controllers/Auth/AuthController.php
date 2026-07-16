<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeOwnPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $theme = $request->validated('theme');
        $this->auth->login($request->validated(), $theme);

        return redirect()->intended(route('home', absolute: false));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showChangePasswordForm(): Response
    {
        return Inertia::render('Profile/ChangePassword', [
            'status' => session('status'),
        ]);
    }

    public function updatePassword(ChangeOwnPasswordRequest $request): RedirectResponse
    {
        $this->auth->updatePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return redirect()
            ->route('profile.password.edit')
            ->with('status', 'Contrasena actualizada correctamente.');
    }
}
