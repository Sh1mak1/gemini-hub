<?php

namespace App\Services\TourismTest;

use Illuminate\Support\Facades\Http;

class NominatimGeocodingService
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';

    private const USER_AGENT = 'gemini-hub-tourism-test/1.0';

    public function geocode(string $query): ?GeocodedPoint
    {
        return $this->request($query);
    }

    public function geocodeNear(string $query, GeocodedPoint $near): ?GeocodedPoint
    {
        $delta = 0.35;
        $viewbox = implode(',', [
            (string) ($near->longitude - $delta),
            (string) ($near->latitude + $delta),
            (string) ($near->longitude + $delta),
            (string) ($near->latitude - $delta),
        ]);

        $nearby = $this->request($query, viewbox: $viewbox, bounded: true);

        if ($nearby !== null) {
            return $nearby;
        }

        return $this->request($query);
    }

    private function request(string $query, ?string $viewbox = null, bool $bounded = false): ?GeocodedPoint
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $params = [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
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

            $first = $results[0];
            $latitude = $first['lat'] ?? null;
            $longitude = $first['lon'] ?? null;

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                return null;
            }

            return new GeocodedPoint((float) $latitude, (float) $longitude);
        } catch (\Throwable) {
            return null;
        }
    }
}
