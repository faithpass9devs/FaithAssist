<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SessionVisitRecorder
{
    public function start(string $sessionId, int $userId, ?string $previousSessionId = null): void
    {
        if ($previousSessionId !== null) {
            $this->preserveLegacySession($previousSessionId);
            $this->close($previousSessionId);
        }

        $session = DB::table('sessions')->where('id', $sessionId)->first();

        if (! $session) {
            return;
        }

        DB::table('session_visit_periods')->updateOrInsert(['session_id' => $sessionId], [
            'user_id' => $userId,
            'device' => $session->device_name,
            'device_model' => $session->device_model,
            'browser' => $session->browser,
            'ip_address' => $session->ip_address,
            'location' => null,
            'started_at' => now(),
            'ended_at' => null,
            'legacy' => false,
        ]);
    }

    public function preserveLegacySession(string $sessionId): void
    {
        if (DB::table('session_visit_periods')->where('session_id', $sessionId)->exists()) {
            return;
        }

        $session = DB::table('sessions')->where('id', $sessionId)->first();
        if (! $session?->user_id || ! $session->last_activity) {
            return;
        }

        $lastActivity = Carbon::createFromTimestamp((int) $session->last_activity, 'UTC');
        DB::table('session_visit_periods')->insert([
            'user_id' => $session->user_id,
            'session_id' => $sessionId,
            'device' => $session->device_name,
            'device_model' => $session->device_model,
            'browser' => $session->browser,
            'ip_address' => $session->ip_address,
            'location' => $session->location_label,
            'started_at' => $lastActivity,
            'ended_at' => $lastActivity,
            'legacy' => true,
        ]);
    }

    public function close(string $sessionId): void
    {
        DB::table('session_visit_periods')
            ->where('session_id', $sessionId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }

    public function captureLoginLocation(
        string $sessionId,
        string $location,
        float $latitude,
        float $longitude,
        ?int $accuracy,
    ): void {
        $period = DB::table('session_visit_periods')
            ->where('session_id', $sessionId)
            ->whereNull('ended_at')
            ->whereNull('login_location')
            ->first();

        if (! $period) {
            return;
        }

        DB::table('session_visit_periods')
            ->where('id', $period->id)
            ->update([
                'login_location' => $location,
                'login_location_accuracy' => $accuracy,
                'location' => $location,
            ]);

        DB::table('session_location_history')->insert([
            'user_id' => $period->user_id,
            'session_id' => $sessionId,
            'kind' => 'login',
            'location' => $location,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'recorded_at' => $period->started_at,
        ]);
    }

    public function captureDeviceModel(string $sessionId, string $deviceModel): void
    {
        DB::table('sessions')
            ->where('id', $sessionId)
            ->whereNull('device_model')
            ->update(['device_model' => $deviceModel]);

        DB::table('session_visit_periods')
            ->where('session_id', $sessionId)
            ->whereNull('ended_at')
            ->whereNull('device_model')
            ->update(['device_model' => $deviceModel]);
    }

    public function recordLocationChange(
        string $sessionId,
        int $userId,
        string $location,
        float $latitude,
        float $longitude,
        ?int $accuracy,
    ): void {
        $period = DB::table('session_visit_periods')
            ->where('session_id', $sessionId)
            ->whereNull('ended_at')
            ->first();

        if (! $period) {
            return;
        }

        $latest = DB::table('session_location_history')
            ->where('session_id', $sessionId)
            ->orderByDesc('recorded_at')
            ->first();

        $locationChanged = $latest && $latest->location !== $location;
        $distance = $latest && $latest->latitude !== null && $latest->longitude !== null
            ? $this->distanceMeters((float) $latest->latitude, (float) $latest->longitude, $latitude, $longitude)
            : null;
        $movementThreshold = max(50, (int) ($latest->accuracy ?? 0), $accuracy ?? 0);

        if (! $latest || $locationChanged || ($distance !== null && $distance >= $movementThreshold)) {
            DB::table('session_location_history')->insert([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'kind' => 'change',
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'recorded_at' => now(),
            ]);
        }

        DB::table('session_visit_periods')->where('id', $period->id)->update(['location' => $location]);
    }

    private function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6_371_000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
