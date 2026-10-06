<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SessionLocationResolver
{
    /**
     * Build the most precise address available for a session.
     */
    public function resolve(
        ?string $ipAddress,
        ?string $assignedMunicipality = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $storedLabel = null
    ): string {
        if ($latitude !== null && $longitude !== null) {
            return $this->fromCoordinates($latitude, $longitude);
        }

        if ($storedLabel !== null && trim($storedLabel) !== '') {
            return $storedLabel;
        }

        return $this->fromIpAddress($ipAddress, $assignedMunicipality);
    }

    public function fromCoordinates(float $latitude, float $longitude): string
    {
        $cacheKey = sprintf('session-location:%.5f:%.5f', $latitude, $longitude);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($latitude, $longitude): string {
            $address = $this->reverseGeocode($latitude, $longitude);

            if ($address === []) {
                return $this->coordinatesLabel($latitude, $longitude);
            }

            $street = trim(implode(' ', array_filter([
                $address['road'] ?? null,
                $address['house_number'] ?? null,
            ])));

            $parts = collect([
                $street !== '' ? $street : null,
                $address['neighbourhood'] ?? $address['suburb'] ?? $address['village'] ?? $address['hamlet'] ?? null,
                $address['city'] ?? $address['town'] ?? $address['municipality'] ?? $address['county'] ?? null,
                isset($address['postcode']) ? 'C.P. '.$address['postcode'] : null,
                $address['state'] ?? null,
                $address['country'] ?? null,
            ])->filter(fn ($part): bool => is_string($part) && trim($part) !== '')
                ->map(fn (string $part): string => trim($part))
                ->unique()
                ->values();

            return $parts->isNotEmpty()
                ? $parts->implode(', ')
                : $this->coordinatesLabel($latitude, $longitude);
        });
    }

    /**
     * @return array<string, string>
     */
    private function reverseGeocode(float $latitude, float $longitude): array
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders(['User-Agent' => config('app.name', 'FaithAssist').' security module'])
                ->timeout(5)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                    'zoom' => 18,
                    'addressdetails' => 1,
                    'accept-language' => 'es',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $address = $response->json('address');

            return is_array($address) ? $address : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function coordinatesLabel(float $latitude, float $longitude): string
    {
        return 'Coordenadas: '.number_format($latitude, 6).', '.number_format($longitude, 6);
    }

    private function fromIpAddress(?string $ipAddress, ?string $assignedMunicipality): string
    {
        $hasMunicipality = $assignedMunicipality && $assignedMunicipality !== 'Sin municipio';

        if (! $ipAddress) {
            return $hasMunicipality
                ? 'Red local · '.$assignedMunicipality
                : 'Ubicación no disponible';
        }

        $isPublicIp = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if (! $isPublicIp) {
            return $hasMunicipality
                ? 'Red local · '.$assignedMunicipality
                : 'Red local';
        }

        return Cache::remember('session-ip-location:'.$ipAddress, now()->addDay(), function () use ($ipAddress): string {
            try {
                $response = Http::acceptJson()
                    ->timeout(3)
                    ->get('https://ipwho.is/'.$ipAddress);

                if (! $response->successful() || ! $response->json('success')) {
                    return 'Ubicación aproximada no disponible';
                }

                $parts = collect([
                    $response->json('city'),
                    $response->json('postal') ? 'C.P. '.$response->json('postal') : null,
                    $response->json('region'),
                    $response->json('country'),
                ])->filter()->values();

                return $parts->isNotEmpty()
                    ? 'Aproximada por IP · '.$parts->implode(', ')
                    : 'Ubicación aproximada no disponible';
            } catch (\Throwable) {
                return 'Ubicación aproximada no disponible';
            }
        });
    }
}
