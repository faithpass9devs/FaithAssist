<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeOwnPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use App\Services\DeviceSessionRegistrar;
use App\Services\SessionLocationResolver;
use App\Services\SessionVisitRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly SessionLocationResolver $locations,
        private readonly DeviceSessionRegistrar $devices,
        private readonly SessionVisitRecorder $visits,
    ) {}

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

        $this->registerDeviceSession($request);

        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');
        $locationAccuracy = $request->validated('location_accuracy');

        if ($latitude !== null && $longitude !== null) {
            $loginLocation = $this->locations->fromCoordinates((float) $latitude, (float) $longitude);
            $request->session()->save();
            DB::table('sessions')
                ->where('id', $request->session()->getId())
                ->update([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location_accuracy' => $locationAccuracy,
                    'location_label' => $loginLocation,
                    'location_updated_at' => now(),
                    'last_seen_at' => now(),
                ]);
            $this->visits->captureLoginLocation(
                $request->session()->getId(),
                $loginLocation,
                (float) $latitude,
                (float) $longitude,
                $locationAccuracy !== null ? (int) round((float) $locationAccuracy) : null,
            );
        }

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
        $sessionId = $request->session()->getId();
        $this->visits->close($sessionId);
        $sessionRecord = DB::table('sessions')->where('id', $sessionId)->first();

        DB::table('sessions')
            ->where('id', $sessionId)
            ->update(['status' => 'closed', 'last_seen_at' => now()]);

        $this->auth->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($sessionRecord) {
            $archivedSession = (array) $sessionRecord;
            $archivedSession['status'] = 'closed';
            $archivedSession['last_seen_at'] = now();
            DB::table('sessions')->updateOrInsert(['id' => $sessionId], $archivedSession);
        }

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
                ->with('status', 'Contraseña actualizada correctamente.');
        }

        return redirect()
            ->route('profile.password.edit')
            ->with('status', 'Contraseña actualizada correctamente.');
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

    private function registerDeviceSession(Request $request): void
    {
        $request->session()->save();

        $userId = $request->user()?->id;

        if ($userId) {
            $browserId = $request->cookie('faithassist_browser_id');
            if (! is_string($browserId) || ! Str::isUuid($browserId)) {
                $browserId = (string) Str::uuid();
                Cookie::queue(cookie('faithassist_browser_id', $browserId, 60 * 24 * 365 * 2, '/', null, null, true, false, 'lax'));
            }

            $this->devices->register(
                $request->session()->getId(),
                $userId,
                $request->userAgent(),
                $browserId,
                $request->validated('device_model'),
            );
        }
    }
}
