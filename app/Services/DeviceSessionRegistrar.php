<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DeviceSessionRegistrar
{
    public function __construct(private readonly SessionVisitRecorder $visits) {}

    /**
     * Keep one record per browser: reactivate the existing one instead of duplicating it.
     */
    public function register(string $sessionId, int $userId, ?string $userAgent, ?string $browserId = null, ?string $deviceModel = null): void
    {
        $userAgent = (string) $userAgent;
        $now = now();

        $metadata = [
            'user_id' => $userId,
            'user_agent' => $userAgent,
            'device_name' => $this->resolveDeviceName($userAgent),
            'device_model' => $deviceModel,
            'operating_system' => $this->resolveOperatingSystem($userAgent),
            'browser' => $this->resolveBrowser($userAgent),
            'browser_id' => $browserId,
            'last_seen_at' => $now,
            'last_activity' => $now->timestamp,
            'status' => 'active',
            'hidden_at' => null,
        ];

        $previous = DB::table('sessions')
            ->where('user_id', $userId)
            ->when($browserId !== null,
                fn ($query) => $query->where('browser_id', $browserId),
                fn ($query) => $query->whereNull('browser_id')->where('user_agent', $userAgent))
            ->where('id', '!=', $sessionId)
            ->orderByDesc('last_activity')
            ->first();

        if (! $previous) {
            DB::table('sessions')
                ->where('id', $sessionId)
                ->update([...$metadata, 'first_seen_at' => $now]);

            $this->visits->start($sessionId, $userId);

            return;
        }

        // Move the existing record onto the new session id so its history is never lost.
        DB::transaction(function () use ($sessionId, $previous, $metadata): void {
            $current = DB::table('sessions')->where('id', $sessionId)->first();

            $this->visits->preserveLegacySession($previous->id);
            $this->visits->close($previous->id);

            DB::table('sessions')->where('id', $sessionId)->delete();

            DB::table('sessions')->where('id', $previous->id)->update([
                ...$metadata,
                'id' => $sessionId,
                'payload' => $current->payload ?? $previous->payload,
                'ip_address' => $current->ip_address ?? $previous->ip_address,
                'first_seen_at' => $previous->first_seen_at ?? $metadata['last_seen_at'],
                'device_model' => $metadata['device_model'] ?: $previous->device_model,
            ]);

            $this->visits->start($sessionId, (int) $metadata['user_id']);
        });
    }

    private function resolveDeviceName(string $userAgent): string
    {
        return preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent) ? 'Dispositivo móvil' : 'Computadora';
    }

    private function resolveOperatingSystem(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Desconocido',
        };
    }

    private function resolveBrowser(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Chrome/') => 'Google Chrome',
            str_contains($userAgent, 'Firefox/') => 'Mozilla Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Navegador desconocido',
        };
    }
}
