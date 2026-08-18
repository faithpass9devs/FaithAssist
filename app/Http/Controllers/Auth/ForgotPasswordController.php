<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmEmailRequest;
use App\Http\Requests\Auth\ConfirmPhoneRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\VerifyPasswordResetCodeRequest;
use App\Services\Auth\ForgotPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    private const SESSION_KEY = 'password_recovery';

    public function __construct(private readonly ForgotPasswordService $forgotPassword) {}

    public function showEmailStep(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPasswordEmail', [
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
        ]);
    }

    public function confirmEmail(ConfirmEmailRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $this->forgotPassword->findUserByEmail($validated['email']);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No se encontró una cuenta con este correo electrónico.',
            ]);
        }

        if (! filled($user->whatsapp_phone)) {
            throw ValidationException::withMessages([
                'email' => 'Esta cuenta no tiene un número de teléfono registrado.',
            ]);
        }

        $registeredPhone = $user->whatsapp_phone;
        $maskedPhone = $this->forgotPassword->maskPhone($registeredPhone);

        $this->putState($request, [
            'started_at' => now()->timestamp,
            'email' => $validated['email'],
            'user_id' => $user->id,
            'phone_normalized' => $registeredPhone,
            'masked_phone' => $maskedPhone,
            'code_verified_at' => null,
        ]);

        return redirect()->route('password.recovery.phone.show');
    }

    public function showPhoneStep(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPasswordPhone', [
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
            'email' => (string) $this->state($request, 'email', ''),
            'countryCodes' => $this->forgotPassword->getCountryCodes(),
            'countryCode' => $this->forgotPassword->getDefaultCountryCode(),
            'registeredPhoneLast4' => $this->forgotPassword->last4Digits((string) $this->state($request, 'phone_normalized', '')),
        ]);
    }

    public function confirmPhone(ConfirmPhoneRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = (int) $this->state($request, 'user_id', 0);

        if ($userId === 0) {
            throw ValidationException::withMessages([
                'email' => 'Sesión expirada. Por favor, inicia nuevamente.',
            ]);
        }

        $user = \App\Models\User::query()->find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Usuario no encontrado.',
            ]);
        }

        $countryCode = preg_replace('/\D/', '', $validated['whatsapp_country_code']) ?: \App\Models\Lada::defaultCode();
        $phoneLocal = preg_replace('/\D/', '', $validated['whatsapp_phone']) ?: '';
        $normalizedPhone = $this->forgotPassword->validatePhoneForUser($user, $phoneLocal, $countryCode);

        if (! $normalizedPhone) {
            throw ValidationException::withMessages([
                'whatsapp_phone' => 'El número de teléfono no es válido.',
            ]);
        }

        $sent = $this->forgotPassword->sendPasswordResetCode($user, $normalizedPhone, $request->ip());

        if (! $sent) {
            throw ValidationException::withMessages([
                'whatsapp_phone' => 'Demasiados intentos fallidos. Por favor, intenta más tarde.',
            ]);
        }

        $this->putState($request, [
            ...$this->state($request),
            'phone_normalized' => $normalizedPhone,
            'masked_phone' => $this->forgotPassword->maskPhone($normalizedPhone),
        ]);

        return redirect()->route('password.recovery.code.show')
            ->with('status', 'Código de recuperación enviado por WhatsApp.');
    }

    public function showCodeStep(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPasswordCode', [
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
            'maskedPhone' => (string) $this->state($request, 'masked_phone', ''),
        ]);
    }

    public function verifyCode(VerifyPasswordResetCodeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = (int) $this->state($request, 'user_id', 0);
        $user = \App\Models\User::query()->find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => 'Sesión expirada. Por favor, inicia nuevamente.',
            ]);
        }

        $verified = $this->forgotPassword->verifyCode($user, $validated['code']);

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'El código ingresado es inválido o ha expirado.',
            ]);
        }

        $this->putState($request, [
            ...$this->state($request),
            'code_verified_at' => now()->timestamp,
        ]);

        return redirect()->route('password.recovery.reset.show')
            ->with('status', 'Código verificado correctamente.');
    }

    public function showResetStep(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPasswordReset', [
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
            'maskedPhone' => (string) $this->state($request, 'masked_phone', ''),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = (int) $this->state($request, 'user_id', 0);
        $user = \App\Models\User::query()->find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'password' => 'Sesión expirada. Por favor, inicia nuevamente.',
            ]);
        }

        $this->forgotPassword->resetPassword($user, $validated['password']);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')->with('status', 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
    }

    private function state(Request $request, ?string $key = null, mixed $default = null): mixed
    {
        $state = $request->session()->get(self::SESSION_KEY, []);

        if (! is_array($state)) {
            return $default;
        }

        if ($key === null) {
            return $state;
        }

        return $state[$key] ?? $default;
    }

    private function putState(Request $request, array $state): void
    {
        $request->session()->put(self::SESSION_KEY, $state);
    }
}
