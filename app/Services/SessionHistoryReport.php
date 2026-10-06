<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionHistoryReport
{
    public function __construct(private readonly SessionLocationResolver $locations) {}

    public function rows(User $user, Carbon $from, Carbon $until): Collection
    {
        $periods = DB::table('session_visit_periods')
            ->where('user_id', $user->id)
            ->whereBetween('started_at', [$from, $until])
            ->orderBy('started_at')
            ->get();

        $ids = $periods->pluck('session_id')->all();
        $locationsBySession = $ids === [] ? collect() : DB::table('session_location_history')
            ->whereIn('session_id', $ids)
            ->whereBetween('recorded_at', [$from, $until])
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('session_id');
        $rows = $periods->map(function (object $period) use ($locationsBySession): array {
            $locationHistory = $locationsBySession->get($period->session_id, collect());
            $lastLocation = $locationHistory->last()?->location;
            $loginLocation = $period->login_location
                ? 'Inicio: '.$period->login_location
                : ($lastLocation
                    ? 'Inicio no verificable · Última ubicación conocida: '.$lastLocation
                    : 'Ubicación no disponible');

            if ($period->login_location && $period->login_location_accuracy) {
                $loginLocation .= ' · Precisión aproximada: '.$period->login_location_accuracy.' m';
            }

            if ($period->legacy) {
                return [
                    'Acceso anterior sin inicio verificable',
                    'Última actividad: '.$this->date($period->ended_at),
                    $this->formatDevice($period->device, $period->device_model),
                    $period->browser ?: 'No registrado',
                    $period->ip_address ?: 'No registrada',
                    $lastLocation
                        ? 'Inicio no verificable · Última ubicación registrada: '.$lastLocation
                        : 'Ubicación inicial no verificable',
                ];
            }

            return [
                $this->date($period->started_at),
                $period->ended_at ? $this->date($period->ended_at) : 'En curso',
                $this->formatDevice($period->device, $period->device_model),
                $period->browser ?: 'No registrado',
                $period->ip_address ?: 'No registrada',
                $loginLocation,
            ];
        });

        $tracked = DB::table('session_visit_periods')
            ->where('user_id', $user->id)
            ->pluck('session_id')
            ->all();
        $legacy = DB::table('sessions')
            ->where('user_id', $user->id)
            ->whereNotIn('id', $tracked)
            ->whereBetween('last_activity', [$from->timestamp, $until->timestamp])
            ->get();

        foreach ($legacy as $session) {
            $rows->push([
                'Acceso anterior sin inicio verificable',
                'Última actividad: '.$this->date(Carbon::createFromTimestamp((int) $session->last_activity, 'UTC')),
                $this->formatDevice($session->device_name, $session->device_model),
                $session->browser ?: 'No registrado',
                $session->ip_address ?: 'No registrada',
                $session->latitude !== null && $session->longitude !== null
                    ? 'Inicio no verificable · Última ubicación GPS registrada: '.$this->locations->fromCoordinates(
                        (float) $session->latitude,
                        (float) $session->longitude,
                    )
                    : 'Ubicación inicial no verificable: el acceso no tiene coordenadas GPS guardadas',
            ]);
        }

        return $rows;
    }

    private function formatDevice(?string $device, ?string $model): string
    {
        $type = $device ?: 'Dispositivo no identificado';

        return $model ? $type.' · '.$model : $type;
    }

    private function date(Carbon|string $date): string
    {
        return Carbon::parse($date, 'UTC')->setTimezone(config('app.display_timezone'))->format('d/m/Y h:i A');
    }
}
