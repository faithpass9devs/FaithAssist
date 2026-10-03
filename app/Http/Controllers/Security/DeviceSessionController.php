<?php

namespace App\Http\Controllers\Security;

use App\Exports\Security\SessionHistoryExport;
use App\Http\Controllers\Controller;
use App\Models\InternalNotification;
use App\Models\ModerationWarning;
use App\Models\User;
use App\Services\Security\DeviceSessionVisibilityService;
use App\Services\SessionHistoryReport;
use App\Services\SessionLocationResolver;
use App\Services\SessionVisitRecorder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeviceSessionController extends Controller
{
    public function __construct(
        private readonly SessionLocationResolver $locations,
        private readonly SessionVisitRecorder $visits,
        private readonly SessionHistoryReport $history,
        private readonly DeviceSessionVisibilityService $visibility,
    ) {
        $this->middleware('can:dispositivos_sesiones.read')->only('index');
        $this->middleware('can:dispositivos_sesiones.delete')->only(['destroy', 'closeUserSessions']);
    }

    /**
     * Store the freshest device coordinates for the signed-in user's own session.
     */
    public function updateOwnLocation(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->hasSession()) {
            return response()->json(['success' => false], 401);
        }

        $data = Validator::validate($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'device_model' => ['nullable', 'string', 'max:120'],
        ]);

        $latitude = round((float) $data['latitude'], 7);
        $longitude = round((float) $data['longitude'], 7);

        $session = DB::table('sessions')
            ->where('id', $request->session()->getId())
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (! $session) {
            $browserId = $request->cookie('faithassist_browser_id');
            $session = is_string($browserId)
                ? DB::table('sessions')
                    ->where('user_id', $request->user()->id)
                    ->where('browser_id', $browserId)
                    ->where('status', 'active')
                    ->orderByDesc('last_activity')
                    ->first()
                : null;
        }

        if (! $session && $request->userAgent()) {
            $matchingAgentSessions = DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('user_agent', $request->userAgent())
                ->where('status', 'active')
                ->limit(2)
                ->get();

            if ($matchingAgentSessions->count() === 1) {
                $session = $matchingAgentSessions->first();
            }
        }

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No se encontró la sesión activa de este navegador.'], 409);
        }

        if (is_string($data['device_model'] ?? null) && trim($data['device_model']) !== '') {
            $this->visits->captureDeviceModel($session->id, trim($data['device_model']));
        }

        $location = $this->locations->fromCoordinates($latitude, $longitude);

        $updated = DB::table('sessions')
            ->where('id', $session->id)
            ->where('user_id', $request->user()->id)
            ->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_accuracy' => isset($data['accuracy']) ? (int) round((float) $data['accuracy']) : null,
                'location_label' => $location,
                'location_updated_at' => now(),
                'last_seen_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['success' => false, 'message' => 'No se encontró la sesión activa del dispositivo.'], 409);
        }

        $this->visits->recordLocationChange(
            $session->id,
            (int) $request->user()->id,
            $location,
            $latitude,
            $longitude,
            isset($data['accuracy']) ? (int) round((float) $data['accuracy']) : null,
        );

        return response()->json(['success' => true]);
    }

    public function index(Request $request): Response
    {
        $viewerIsSuperadmin = $request->user()?->hasRole('Superadmin') ?? false;
        $visibleUserIds = $this->visibility->visibleUserIds($request->user());

        $sessions = DB::table('sessions')
            ->select([
                'sessions.id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.device_name',
                'sessions.device_model',
                'sessions.operating_system',
                'sessions.browser',
                'sessions.first_seen_at',
                'sessions.last_seen_at',
                'sessions.last_activity',
                'sessions.status',
                'sessions.latitude',
                'sessions.longitude',
                'sessions.location_accuracy',
                'sessions.location_label',
                'users.name as user_name',
                'users.email',
                'profiles.name as profile_name',
                'profiles.paterno as profile_paterno',
                'profiles.materno as profile_materno',
            ])
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'sessions.user_id')
            ->whereIn('sessions.user_id', $visibleUserIds)
            ->whereNull('sessions.hidden_at')
            ->orderByDesc('sessions.last_activity')
            ->get();

        $users = User::query()
            ->whereIn('id', $visibleUserIds)
            ->with(['profile', 'church.municipality', 'roles'])
            ->get()
            ->keyBy('id');
        $userIds = $users->keys()->values();
        $warningCounts = ModerationWarning::query()
            ->selectRaw('user_id, count(*) as total')
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');
        $warningsByUser = ModerationWarning::query()
            ->with('issuer.profile')
            ->whereIn('user_id', $userIds)
            ->latest()
            ->get()
            ->groupBy('user_id');

        $normalizedSessions = $sessions->map(function ($session) use ($users, $viewerIsSuperadmin, $warningCounts, $warningsByUser): array {
            $profileName = trim(collect([
                $session->profile_name,
                $session->profile_paterno,
                $session->profile_materno,
            ])->filter()->implode(' '));
            $displayName = $profileName !== '' ? $profileName : trim((string) $session->user_name);

            $user = $users->get($session->user_id);
            $role = $user?->roles?->first()?->name ?? 'Sin rol';
            $parish = $user?->church?->name
                ?? ($viewerIsSuperadmin ? 'Acceso total' : 'Sin parroquia');
            $municipality = $user?->church?->municipality?->name ?? 'Sin municipio';

            return [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'user' => $displayName !== '' ? $displayName : $session->email,
                'email' => $session->email,
                'parish' => $parish,
                'role' => $role,
                'account_status' => $user?->account_status ?? 'active',
                'suspended_until' => $user?->suspended_until?->format('d/m/Y h:i A'),
                'warning_count' => (int) ($warningCounts[$session->user_id] ?? 0),
                'warnings' => $this->serializeWarnings($warningsByUser->get($session->user_id, collect())),
                'municipality' => $municipality,
                'device' => $session->device_name ?: 'Dispositivo desconocido',
                'device_model' => $session->device_model,
                'device_type' => $this->resolveDeviceType($session->user_agent),
                'operating_system' => $session->operating_system ?: 'Desconocido',
                'browser' => $session->browser ?: 'Desconocido',
                'ip_address' => $session->ip_address ?: 'N/D',
                'location' => $this->resolveLocation($session->ip_address, $municipality, $session->latitude, $session->longitude, $session->location_label ?? null),
                'first_seen_at' => $this->displayDate($session->first_seen_at),
                'last_activity_timestamp' => (int) ($session->last_activity ?? 0),
                'last_activity' => $this->displayDate($session->last_activity ? (int) $session->last_activity : null),
                'last_seen_at' => $this->displayDate($session->last_seen_at),
                'status' => $session->status ?? 'active',
                'last_activity_elapsed' => $this->formatElapsed($session->last_activity),
                'user_agent' => $session->user_agent,
            ];
        })->values();

        $normalized = $normalizedSessions
            ->groupBy(fn (array $session): string => $session['user_id']
                ? 'user:'.$session['user_id']
                : 'session:'.$session['id'])
            ->map(function ($userSessions): array {
                $latest = $userSessions->sortByDesc('last_activity_timestamp')->first();
                $activeSessions = $userSessions->where('status', 'active');
                // Several browsers on the same machine are still one device.
                $deviceCount = $activeSessions
                    ->unique(fn (array $session): string => implode('|', [
                        $session['device'],
                        $session['operating_system'],
                        $session['ip_address'],
                    ]))
                    ->count();

                return [
                    ...$latest,
                    'device_count' => $deviceCount,
                    'session_count' => $activeSessions->count(),
                    'devices' => $userSessions->values()->all(),
                    'status' => $userSessions->contains(fn (array $session): bool => $session['status'] === 'active')
                        ? 'active'
                        : ($latest['status'] ?? 'inactive'),
                ];
            })
            ->values()
            ->values();

        $usersWithoutSessions = $users
            ->reject(fn (User $user): bool => $normalized->contains(fn (array $session): bool => (int) $session['user_id'] === (int) $user->id))
            ->map(function (User $user) use ($viewerIsSuperadmin, $warningCounts, $warningsByUser): array {
                $profileName = trim(collect([
                    $user->profile?->name,
                    $user->profile?->paterno,
                    $user->profile?->materno,
                ])->filter()->implode(' '));
                $displayName = $profileName !== '' ? $profileName : $user->name;

                return [
                    'id' => 'user:'.$user->id,
                    'user_id' => $user->id,
                    'user' => $displayName !== '' ? $displayName : $user->email,
                    'email' => $user->email,
                    'parish' => $user->church?->name ?? ($viewerIsSuperadmin ? 'Acceso total' : 'Sin parroquia'),
                    'role' => $user->roles?->first()?->name ?? 'Sin rol',
                    'account_status' => $user->account_status ?? 'active',
                    'suspended_until' => $user->suspended_until?->format('d/m/Y h:i A'),
                    'warning_count' => (int) ($warningCounts[$user->id] ?? 0),
                    'warnings' => $this->serializeWarnings($warningsByUser->get($user->id, collect())),
                    'municipality' => $user->church?->municipality?->name ?? 'Sin municipio',
                    'device' => 'Sin dispositivos',
                    'device_type' => 'N/D',
                    'operating_system' => 'N/D',
                    'browser' => 'N/D',
                    'ip_address' => 'N/D',
                    'location' => 'N/D',
                    'first_seen_at' => 'N/D',
                    'last_activity_timestamp' => 0,
                    'last_activity' => 'N/D',
                    'last_seen_at' => 'N/D',
                    'status' => 'inactive',
                    'last_activity_elapsed' => 'Sin sesiones activas',
                    'user_agent' => null,
                    'device_count' => 0,
                    'session_count' => 0,
                    'devices' => [],
                ];
            });

        $normalized = $normalized->concat($usersWithoutSessions)->values();

        $deviceKeys = $sessions->map(fn ($session) => implode('|', [
            $session->user_id,
            $session->device_name,
            $session->operating_system,
            $session->ip_address,
        ]))->unique();

        $summary = [
            'users' => $users->count(),
            'sessions' => $sessions->count(),
            'devices' => $deviceKeys->count(),
            'active' => $sessions->where('status', 'active')->count(),
        ];

        return Inertia::render('Security/DeviceSessions/Index', [
            'sessions' => $normalized,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, User $usuario): Response
    {
        abort_unless($request->user()?->can('dispositivos_sesiones.read'), 403);
        abort_unless($this->visibility->canView($request->user(), $usuario), 404);

        $viewerIsSuperadmin = $request->user()?->hasRole('Superadmin') ?? false;
        $usuario->load(['profile', 'church.municipality', 'roles']);
        $warningCount = ModerationWarning::query()->where('user_id', $usuario->id)->count();
        $sessions = DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->whereNull('hidden_at')
            ->orderByDesc('last_activity')
            ->get();
        $loginLocations = DB::table('session_visit_periods')
            ->whereIn('session_id', $sessions->pluck('id'))
            ->get(['session_id', 'login_location', 'login_location_accuracy'])
            ->keyBy('session_id');
        $name = trim(collect([
            $usuario->profile?->name,
            $usuario->profile?->paterno,
            $usuario->profile?->materno,
        ])->filter()->implode(' ')) ?: $usuario->name;
        $role = $usuario->roles->first()?->name ?? 'Sin rol';
        $parish = $usuario->church?->name ?? ($viewerIsSuperadmin ? 'Acceso total' : 'Sin parroquia');
        $municipality = $usuario->church?->municipality?->name ?? 'Sin municipio';

        $normalized = $sessions->map(function ($session) use ($name, $usuario, $role, $parish, $municipality, $warningCount, $loginLocations): array {
            $isActive = ($session->status ?? 'active') === 'active';
            $isOnline = $isActive && (int) ($session->last_activity ?? 0) >= now()->subMinutes(2)->timestamp;
            $loginLocation = $loginLocations->get($session->id);
            $hasGpsUpdate = $session->location_updated_at !== null;
            $latestGpsLocation = $hasGpsUpdate && $session->latitude !== null && $session->longitude !== null
                ? $this->locations->fromCoordinates((float) $session->latitude, (float) $session->longitude)
                : null;

            return [
                'id' => $session->id,
                'user_id' => $usuario->id,
                'user' => $name,
                'email' => $usuario->email,
                'parish' => $parish,
                'role' => $role,
                'account_status' => $usuario->account_status ?? 'active',
                'suspended_until' => $usuario->suspended_until?->format('d/m/Y h:i A'),
                'warning_count' => $warningCount,
                'municipality' => $municipality,
                'device' => $session->device_name ?: 'Dispositivo desconocido',
                'device_model' => $session->device_model,
                'device_type' => $this->resolveDeviceType($session->user_agent),
                'browser' => $session->browser ?: 'Navegador desconocido',
                'operating_system' => $session->operating_system ?: 'Desconocido',
                'ip_address' => $session->ip_address ?: 'N/D',
                'location' => $latestGpsLocation
                    ?? $loginLocation?->login_location
                    ?? 'Ubicación pendiente de una captura GPS',
                'location_accuracy' => $hasGpsUpdate && $session->location_accuracy
                    ? 'Precisión aproximada: '.$session->location_accuracy.' m'
                    : ($loginLocation?->login_location_accuracy ? 'Precisión aproximada: '.$loginLocation->login_location_accuracy.' m' : null),
                'first_seen_at' => $this->displayDate($session->first_seen_at),
                'last_activity' => $this->displayDate($session->last_activity ? (int) $session->last_activity : null),
                'status' => $session->status ?? 'active',
                'is_online' => $isOnline,
                'last_activity_elapsed' => $this->formatElapsed($session->last_activity),
            ];
        })->values();

        return Inertia::render('Security/DeviceSessions/Show', [
            'reportMonth' => now()->setTimezone(config('app.display_timezone'))->format('Y-m'),
            'reportStartMonth' => $usuario->created_at?->setTimezone(config('app.display_timezone'))->format('Y-m') ?? now()->setTimezone(config('app.display_timezone'))->format('Y-m'),
            'reportToday' => now()->setTimezone(config('app.display_timezone'))->format('Y-m-d'),
            'user' => [
                'id' => $usuario->id,
                'name' => $name,
                'email' => $usuario->email,
                'parish' => $parish,
                'role' => $role,
                'account_status' => $usuario->account_status ?? 'active',
                'suspended_until' => $usuario->suspended_until?->format('d/m/Y h:i A'),
                'warning_count' => $warningCount,
            ],
            'sessions' => $normalized,
        ]);
    }

    public function sendWarning(Request $request, User $usuario): JsonResponse
    {
        abort_unless($request->user()?->can('moderacion_cuentas.create'), 403);
        abort_unless($this->visibility->canView($request->user(), $usuario), 404);

        $data = Validator::validate($request->all(), [
            'level' => ['required', 'in:leve,moderada,grave'],
            'reason' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $warning = ModerationWarning::create([
            'user_id' => $usuario->id,
            'issued_by' => $request->user()->id,
            'level' => $data['level'],
            'reason' => $data['reason'] ?? null,
            'message' => $data['message'],
        ]);

        $this->applyLevelToAccount($usuario, $data['level']);

        $activeIds = DB::table('sessions')->where('user_id', $usuario->id)->where('status', 'active')->pluck('id');
        DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->where('status', 'active')
            ->update(['status' => 'closed', 'last_seen_at' => now()]);

        foreach ($activeIds as $sessionId) {
            $this->visits->close($sessionId);
        }

        InternalNotification::create([
            'user_id' => $usuario->id,
            'type' => 'moderation_warning',
            'title' => 'Advertencia de uso indebido',
            'message' => $data['message'],
            'data' => ['warning_id' => $warning->id, 'level' => $data['level']],
        ]);

        activity('moderacion_cuentas')
            ->causedBy($request->user())
            ->performedOn($usuario)
            ->withProperties([
                'warning_id' => $warning->id,
                'level' => $data['level'],
                'reason' => $data['reason'] ?? null,
                'message' => $data['message'],
            ])
            ->log('Advertencia de moderación emitida');

        return response()->json([
            'success' => true,
            'message' => 'La advertencia fue registrada y notificada al usuario.',
            'level' => $data['level'],
            'warning_count' => ModerationWarning::query()->where('user_id', $usuario->id)->count(),
            'account_status' => $usuario->account_status,
            'suspended_until' => $usuario->suspended_until?->format('d/m/Y h:i A'),
        ]);
    }

    public function updateAccountStatus(Request $request, User $usuario): JsonResponse
    {
        abort_unless($request->user()?->can('moderacion_cuentas.update'), 403);
        abort_unless($this->visibility->canView($request->user(), $usuario), 404);

        $data = Validator::validate($request->all(), [
            'account_status' => ['required', 'in:active,suspended,blocked'],
        ]);

        $usuario->forceFill([
            'account_status' => $data['account_status'],
            'suspended_until' => null,
        ])->save();

        activity('moderacion_cuentas')
            ->causedBy($request->user())
            ->performedOn($usuario)
            ->withProperties(['account_status' => $data['account_status']])
            ->log('Estado de cuenta actualizado manualmente');

        return response()->json([
            'success' => true,
            'account_status' => $usuario->account_status,
        ]);
    }

    public function deleteWarning(Request $request, User $usuario, ModerationWarning $warning): JsonResponse
    {
        abort_unless($request->user()?->can('moderacion_cuentas.update'), 403);
        abort_unless($this->visibility->canView($request->user(), $usuario), 404);
        abort_unless((int) $warning->user_id === (int) $usuario->id, 404);

        InternalNotification::query()
            ->where('user_id', $usuario->id)
            ->where('type', 'moderation_warning')
            ->whereJsonContains('data->warning_id', $warning->id)
            ->delete();

        $warning->delete();

        $remaining = ModerationWarning::query()->where('user_id', $usuario->id)->latest()->get();
        $highestRemaining = $remaining->first(fn (ModerationWarning $w): bool => $w->level === 'grave')
            ?? $remaining->first(fn (ModerationWarning $w): bool => $w->level === 'moderada');

        if ($highestRemaining) {
            $this->applyLevelToAccount($usuario, $highestRemaining->level, resetSuspensionTimer: false);
        } else {
            $usuario->forceFill(['account_status' => 'active', 'suspended_until' => null])->save();
        }

        activity('moderacion_cuentas')
            ->causedBy($request->user())
            ->performedOn($usuario)
            ->withProperties(['warning_id' => $warning->id])
            ->log('Advertencia de moderación eliminada');

        return response()->json([
            'success' => true,
            'warning_count' => $remaining->count(),
            'account_status' => $usuario->account_status,
        ]);
    }

    private function applyLevelToAccount(User $usuario, string $level, bool $resetSuspensionTimer = true): void
    {
        match ($level) {
            'grave' => $usuario->forceFill(['account_status' => 'blocked', 'suspended_until' => null])->save(),
            'moderada' => $usuario->forceFill([
                'account_status' => 'suspended',
                'suspended_until' => $resetSuspensionTimer ? now()->addHours(24) : ($usuario->suspended_until ?? now()->addHours(24)),
            ])->save(),
            default => null,
        };
    }

    private function serializeWarnings($warnings): array
    {
        return collect($warnings)->map(function (ModerationWarning $warning): array {
            $issuerName = trim(collect([
                $warning->issuer?->profile?->name,
                $warning->issuer?->profile?->paterno,
            ])->filter()->implode(' ')) ?: $warning->issuer?->name ?? 'N/D';

            return [
                'id' => $warning->id,
                'level' => $warning->level,
                'reason' => $warning->reason,
                'message' => $warning->message,
                'issuer' => $issuerName,
                'date' => $warning->created_at?->format('d/m/Y h:i A') ?? 'N/D',
            ];
        })->values()->all();
    }

    private function resolveDeviceType(?string $userAgent): string
    {
        $agent = strtolower((string) $userAgent);

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet') || str_contains($agent, 'android') && ! str_contains($agent, 'mobile')) {
            return 'Tableta';
        }

        if (str_contains($agent, 'mobile') || str_contains($agent, 'iphone') || str_contains($agent, 'ipod') || str_contains($agent, 'android')) {
            return 'Teléfono';
        }

        if ($agent !== '') {
            return 'Computadora';
        }

        return 'Dispositivo no identificado';
    }

    private function formatElapsed(?int $timestamp): string
    {
        if (! $timestamp) {
            return 'Sin actividad';
        }

        $seconds = max(0, now()->timestamp - $timestamp);

        if ($seconds < 60) {
            return 'Hace menos de un minuto';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return 'Hace '.$minutes.' '.($minutes === 1 ? 'minuto' : 'minutos');
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return 'Hace '.$hours.' '.($hours === 1 ? 'hora' : 'horas');
        }

        $days = intdiv($hours, 24);

        return 'Hace '.$days.' '.($days === 1 ? 'día' : 'días');
    }

    private function resolveLocation(?string $ipAddress, ?string $assignedMunicipality = null, ?float $latitude = null, ?float $longitude = null, ?string $storedLabel = null): string
    {
        return $this->locations->resolve($ipAddress, $assignedMunicipality, $latitude, $longitude, $storedLabel);
    }

    /**
     * Render a stored UTC moment in the timezone the users actually live in.
     */
    private function displayDate(Carbon|string|int|null $moment): string
    {
        if ($moment === null || $moment === '') {
            return 'N/D';
        }

        $date = is_int($moment) ? Carbon::createFromTimestamp($moment, 'UTC') : Carbon::parse($moment, 'UTC');

        return $date->setTimezone(config('app.display_timezone'))->format('d/m/Y h:i A');
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        abort_unless($request->user()?->can('dispositivos_sesiones.delete'), 403);

        $sessionRecord = DB::table('sessions')->where('id', $session)->first();
        abort_unless(
            $sessionRecord?->user_id && $this->visibility->canView($request->user(), User::query()->findOrFail($sessionRecord->user_id)),
            404,
        );
        // The row stays in place so the activity keeps feeding the PDF history.
        $deleted = DB::table('sessions')
            ->where('id', $session)
            ->whereNull('hidden_at')
            ->update(['status' => 'closed', 'last_seen_at' => now(), 'hidden_at' => now()]);

        if ($deleted && $sessionRecord?->user_id) {
            $this->visits->close($session);
            activity('dispositivos_sesiones')
                ->causedBy($request->user())
                ->withProperties(['session_id' => $session, 'user_id' => $sessionRecord->user_id])
                ->log('Cierre de sesión de dispositivo');
        }

        return response()->json([
            'success' => (bool) $deleted,
            'message' => $deleted ? 'Sesión eliminada de la vista. El historial se conserva para el reporte.' : 'No se encontró la sesión.',
        ], $deleted ? 200 : 404);
    }

    public function closeUserSessions(Request $request, User $usuario): JsonResponse
    {
        abort_unless($request->user()?->can('dispositivos_sesiones.delete'), 403);
        abort_unless($this->visibility->canView($request->user(), $usuario), 404);

        $sessionIds = DB::table('sessions')->where('user_id', $usuario->id)->whereNull('hidden_at')->pluck('id');
        $deleted = DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->whereNull('hidden_at')
            ->update(['status' => 'closed', 'last_seen_at' => now(), 'hidden_at' => now()]);

        if ($deleted) {
            foreach ($sessionIds as $sessionId) {
                $this->visits->close($sessionId);
            }
            activity('dispositivos_sesiones')
                ->causedBy($request->user())
                ->withProperties(['user_id' => $usuario->id])
                ->log('Cierre de todas las sesiones del usuario');
        }

        return response()->json([
            'success' => (bool) $deleted,
            'message' => $deleted
                ? 'Se cerraron las sesiones del usuario en todos sus dispositivos. El historial se conserva para el reporte.'
                : 'No había sesiones para cerrar.',
        ]);
    }

    public function downloadHistory(Request $request, User $usuario): BinaryFileResponse
    {
        abort_unless($request->user()?->can('dispositivos_sesiones.read'), 403);
        abort_unless($this->visibility->canView($request->user(), $usuario), 404);

        $today = now()->setTimezone(config('app.display_timezone'));
        $data = Validator::validate($request->all(), [
            'month' => ['nullable', 'date_format:Y-m', 'prohibits:from,to', function (string $attribute, mixed $value, \Closure $fail) use ($today): void {
                if (is_string($value) && $value > $today->format('Y-m')) {
                    $fail('No se puede generar un reporte de un mes futuro.');
                }
            }],
            'from' => ['required_with:to', 'date_format:Y-m-d', 'prohibits:month'],
            'to' => ['required_with:from', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$today->format('Y-m-d'), 'prohibits:month'],
        ]);
        $timezone = config('app.display_timezone');
        if (isset($data['from'], $data['to'])) {
            $start = Carbon::createFromFormat('!Y-m-d', $data['from'], $timezone)->startOfDay();
            $end = Carbon::createFromFormat('!Y-m-d', $data['to'], $timezone)->endOfDay();
        } else {
            $start = Carbon::createFromFormat('!Y-m', $data['month'] ?? now()->setTimezone($timezone)->format('Y-m'), $timezone)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        $usuario->load(['profile', 'church.municipality', 'roles']);
        $name = trim(collect([
            $usuario->profile?->name,
            $usuario->profile?->paterno,
            $usuario->profile?->materno,
        ])->filter()->implode(' ')) ?: $usuario->name;
        $export = new SessionHistoryExport([
            'name' => $name,
            'email' => $usuario->email,
            'phone' => $usuario->whatsapp_phone ?: 'No registrado',
            'parish' => $usuario->church?->name ?? 'Sin parroquia',
            'role' => $usuario->roles->first()?->name ?? 'Sin rol',
            'status' => $this->statusLabel($usuario->account_status ?? 'active'),
        ], $start->format('d/m/Y').' - '.$end->format('d/m/Y'), $this->history->rows(
            $usuario,
            $start->copy()->utc(),
            $end->copy()->utc(),
        ));

        $userFileName = Str::slug($name) ?: 'usuario-'.$usuario->id;

        return Excel::download($export, 'historial-sesiones-'.$userFileName.'-'.$start->format('Ymd').'-'.$end->format('Ymd').'.xlsx');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'closed' => 'Cerrado',
            default => ucfirst($status),
        };
    }
}
