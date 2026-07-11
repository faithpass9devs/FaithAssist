<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeOwnPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
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

        return redirect()->intended(route('home', absolute: false));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

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
        Auth::logoutOtherDevices($request->validated('current_password'));

        $request->user()->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->save();

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
