<?php

namespace App\Services\TourismTest;

use Illuminate\Support\Facades\Http;

class NominatimGeocodingService
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';

    private const USER_AGENT = 'gemini-hub-tourism-test/1.0';

    private const RESULT_LIMIT = 5;

    public function geocode(string $query): ?GeocodedPoint
    {
        return $this->request($query);
    }

    public function geocodeNear(string $query, GeocodedPoint $near, ?float $expectedDistanceKm = null): ?GeocodedPoint
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $delta = $this->viewboxDelta($expectedDistanceKm);
        $viewbox = implode(',', [
            (string) ($near->longitude - $delta),
            (string) ($near->latitude + $delta),
            (string) ($near->longitude + $delta),
            (string) ($near->latitude - $delta),
        ]);

        $nearby = $this->request($query, viewbox: $viewbox, bounded: true, expectedDistanceKm: $expectedDistanceKm, near: $near);

        if ($nearby !== null) {
            return $nearby;
        }

        return $this->request($query, expectedDistanceKm: $expectedDistanceKm, near: $near);
    }

    private function viewboxDelta(?float $expectedDistanceKm): float
    {
        $km = $expectedDistanceKm ?? 25;
        $radiusKm = $km + max(3.0, $km * 0.5);

        return min(0.4, max(0.06, $radiusKm / 111.0));
    }

    private function request(
        string $query,
        ?string $viewbox = null,
        bool $bounded = false,
        ?float $expectedDistanceKm = null,
        ?GeocodedPoint $near = null,
    ): ?GeocodedPoint {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $params = [
            'q' => $query,
            'format' => 'json',
            'limit' => self::RESULT_LIMIT,
            'countrycodes' => 'jp',
        ];

        if ($viewbox !== null) {
            $params['viewbox'] = $viewbox;
            $params['bounded'] = $bounded ? 1 : 0;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::BASE_URL, $params);

            if (! $response->successful()) {
                return null;
            }

            $results = $response->json();

            if (! is_array($results) || $results === []) {
                return null;
            }

            return $this->pickBestResult($results, $near, $expectedDistanceKm);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function pickBestResult(array $results, ?GeocodedPoint $near, ?float $expectedDistanceKm): ?GeocodedPoint
    {
        $candidates = [];

        foreach ($results as $result) {
            $latitude = $result['lat'] ?? null;
            $longitude = $result['lon'] ?? null;

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                continue;
            }

            $point = new GeocodedPoint((float) $latitude, (float) $longitude);
            $distanceKm = $near !== null
                ? $this->haversineKm($near, $point)
                : 0.0;

            $candidates[] = [
                'point' => $point,
                'distanceKm' => $distanceKm,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        if ($near !== null && $expectedDistanceKm !== null && $expectedDistanceKm > 0) {
            $maxAllowed = max($expectedDistanceKm * 2.5, $expectedDistanceKm + 10);

            $candidates = array_values(array_filter(
                $candidates,
                fn (array $candidate) => $candidate['distanceKm'] <= $maxAllowed,
            ));

            if ($candidates === []) {
                return null;
            }

            usort(
                $candidates,
                fn (array $left, array $right) => abs($left['distanceKm'] - $expectedDistanceKm)
                    <=> abs($right['distanceKm'] - $expectedDistanceKm),
            );

            return $candidates[0]['point'];
        }

        if ($near !== null) {
            usort(
                $candidates,
                fn (array $left, array $right) => $left['distanceKm'] <=> $right['distanceKm'],
            );
        }

        return $candidates[0]['point'];
    }

    private function haversineKm(GeocodedPoint $from, GeocodedPoint $to): float
    {
        $earthRadiusKm = 6371.0;
        $latFrom = deg2rad($from->latitude);
        $latTo = deg2rad($to->latitude);
        $deltaLat = deg2rad($to->latitude - $from->latitude);
        $deltaLon = deg2rad($to->longitude - $from->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($deltaLon / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
