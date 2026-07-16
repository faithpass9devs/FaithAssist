<?php

namespace App\Services\Auth;

use App\Models\Lada;
use App\Models\PasswordResetWhatsappCode;
use App\Models\User;
use App\Services\MetaWhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class ForgotPasswordService
{
    public function __construct(private readonly MetaWhatsAppService $whatsapp) {}

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
        $rateLimitKey = sprintf('password-reset:%d|%s', $user->id, $ip);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return false;
        }

        $code = (string) random_int(100000, 999999);
        $codeHash = Hash::make($code);

        DB::transaction(function () use ($user, $codeHash): void {
            PasswordResetWhatsappCode::query()
                ->where('user_id', $user->id)
                ->delete();

            PasswordResetWhatsappCode::create([
                'user_id' => $user->id,
                'code_hash' => $codeHash,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(15),
            ]);
        });

        try {
            $this->whatsapp->sendMessage(
                phone: $normalizedPhone,
                message: "Tu código de recuperación de contraseña es: {$code}\n\nVálido por 15 minutos. No compartas este código con nadie."
            );
            RateLimiter::hit($rateLimitKey, 600);
            return true;
        } catch (Throwable $e) {
            report($e);
            return false;
        }
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
