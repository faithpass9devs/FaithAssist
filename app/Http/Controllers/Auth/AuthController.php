<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeOwnPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        $palette = $request->validated('palette');
        $customColor = $request->validated('custom_color');

        $this->auth->login(
            $request->validated(),
            $theme
        );

        if ($request->user() && ($theme || $palette || $customColor)) {
            $columns = $this->availableUiColumns();

            $updates = [];

            if (in_array('ui_theme', $columns, true)) {
                $updates['ui_theme'] = $theme ?? $request->user()->ui_theme;
            }

            if (in_array('ui_palette', $columns, true) && $palette !== null) {
                $updates['ui_palette'] = $palette;
            }

            if (in_array('ui_custom_color', $columns, true) && $customColor !== null) {
                $updates['ui_custom_color'] = $customColor;
            }

            if ($updates !== []) {
                $request->user()->forceFill($updates)->save();
            }
        }

        if ($request->user()?->must_change_password) {
            return redirect()
                ->route('profile.password.edit')
                ->with('status', 'Debes actualizar tu contraseña para ingresar al sistema.');
        }

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
            'forceChange' => (bool) auth()->user()?->must_change_password,
        ]);
    }

    public function updatePassword(ChangeOwnPasswordRequest $request): RedirectResponse
    {
        $mustChangePassword = (bool) $request->user()->must_change_password;

        $this->auth->updatePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        if ($mustChangePassword) {
            return redirect()
                ->route('home')
                ->with('status', 'Contrasena actualizada correctamente.');
        }

        return redirect()
            ->route('profile.password.edit')
            ->with('status', 'Contrasena actualizada correctamente.');
    }

    private function availableUiColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        $columns = [];

        foreach (['ui_theme', 'ui_palette', 'ui_custom_color'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }
}
