<?php

namespace App\Services\Auth;

use App\Models\Lada;
use App\Models\PasswordResetWhatsappCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordService
{
    public function findUserByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function validatePhoneForUser(User $user, string $phoneLocal, string $countryCode): ?string
    {
        $normalizedPhone = Lada::normalizeLocal($phoneLocal, $countryCode);

        if (! $normalizedPhone) {
            return null;
        }

        return $normalizedPhone;
    }

    public function sendPasswordResetCode(User $user, string $normalizedPhone, string $ip): bool
    {
        return false;
    }

    public function verifyCode(User $user, string $inputCode): bool
    {
        $resetCode = PasswordResetWhatsappCode::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $resetCode || $resetCode->expires_at->isPast()) {
            return false;
        }

        if ($resetCode->attempts >= 5) {
            return false;
        }

        $resetCode->increment('attempts');

        return Hash::check($inputCode, $resetCode->code_hash);
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        PasswordResetWhatsappCode::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    public function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return $phone;
        }

        $visible = substr($digits, -4);
        return '*** *** ' . $visible;
    }

    public function last4Digits(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }

    public function getCountryCodes(): array
    {
        return Lada::options();
    }

    public function getDefaultCountryCode(): string
    {
        return Lada::defaultCode();
    }
}
