<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(array $validated, ?string $theme = null): void
    {
        Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        if ($theme && Auth::user()?->ui_theme !== $theme) {
            Auth::user()->forceFill([
                'ui_theme' => $theme,
            ])->save();
        }
    }

    public function updatePassword(User $user, string $currentPassword, string $newPassword): void
    {
        Auth::logoutOtherDevices($currentPassword);

        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }
}
