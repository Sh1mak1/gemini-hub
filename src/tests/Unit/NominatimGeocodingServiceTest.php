<?php

namespace Tests\Unit;

use App\Services\TourismTest\NominatimGeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NominatimGeocodingServiceTest extends TestCase
{
    public function test_geocode_returns_coordinates_from_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '36.5780000',
                    'lon' => '136.6480000',
                ],
            ]),
        ]);

        $point = app(NominatimGeocodingService::class)->geocode('金沢駅');

        $this->assertNotNull($point);
        $this->assertSame(36.578, $point->latitude);
        $this->assertSame(136.648, $point->longitude);
    }

    public function test_geocode_returns_null_when_nominatim_has_no_results(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([]),
        ]);

        $point = app(NominatimGeocodingService::class)->geocode('存在しない地点');

        $this->assertNull($point);
    }

    public function test_geocode_near_picks_result_closest_to_expected_distance(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'lat' => '35.0000000',
                    'lon' => '140.0000000',
                ],
                [
                    'lat' => '35.7147650',
                    'lon' => '139.7966550',
                ],
            ]),
        ]);

        $center = new \App\Services\TourismTest\GeocodedPoint(35.681236, 139.767125);
        $point = app(NominatimGeocodingService::class)->geocodeNear('浅草寺', $center, 5.0);

        $this->assertNotNull($point);
        $this->assertSame(35.714765, $point->latitude);
        $this->assertSame(139.796655, $point->longitude);
    }
}
