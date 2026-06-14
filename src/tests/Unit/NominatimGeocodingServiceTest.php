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
}
