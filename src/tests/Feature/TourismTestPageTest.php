<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Gemini\GeminiClient;
use App\Services\TourismTest\GeocodedPoint;
use App\Services\TourismTest\NominatimGeocodingService;
use App\Services\TourismTest\WikipediaImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TourismTestPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_guest_cannot_view_tourism_test_page(): void
    {
        $this->get(route('debug.tourism'))->assertRedirect(route('login'));
        $this->postJson(route('debug.tourism.search'), [
            'location_name' => '金沢駅',
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_tourism_test_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('debug.tourism'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Debug/Tourism')
                ->has('recentSearches', 0)
                ->where('latestResult', null)
                ->where('error', null)
            );
    }

    public function test_search_persists_spots_and_returns_json_results(): void
    {
        $user = User::factory()->create();

        $gemini = Mockery::mock(GeminiClient::class);
        $gemini->shouldReceive('generateStructured')
            ->once()
            ->andReturn([
                'spots' => [
                    [
                        'name' => '兼六園',
                        'distance_km' => 2.1,
                        'distance_text' => '約 2.1km',
                        'description' => '日本三名園のひとつ。',
                    ],
                    [
                        'name' => '金沢21世紀美術館',
                        'distance_km' => 2.5,
                        'distance_text' => '約 2.5km',
                        'description' => '現代アートの美術館。',
                    ],
                    [
                        'name' => '東茶屋街',
                        'distance_km' => 3.0,
                        'distance_text' => '約 3.0km',
                        'description' => '歴史的な茶屋街。',
                    ],
                ],
            ]);

        $this->app->instance(GeminiClient::class, $gemini);

        $wikipedia = Mockery::mock(WikipediaImageResolver::class);
        $wikipedia->shouldReceive('resolve')
            ->times(3)
            ->andReturn(null);

        $this->app->instance(WikipediaImageResolver::class, $wikipedia);

        $geocoding = Mockery::mock(NominatimGeocodingService::class);
        $geocoding->shouldReceive('geocode')
            ->once()
            ->with('金沢駅')
            ->andReturn(new GeocodedPoint(36.578, 136.648));
        $geocoding->shouldReceive('geocodeNear')
            ->once()
            ->with('兼六園, 金沢駅', Mockery::type(GeocodedPoint::class))
            ->andReturn(new GeocodedPoint(36.562, 136.662));
        $geocoding->shouldReceive('geocodeNear')
            ->once()
            ->with('金沢21世紀美術館, 金沢駅', Mockery::type(GeocodedPoint::class))
            ->andReturn(new GeocodedPoint(36.560, 136.658));
        $geocoding->shouldReceive('geocodeNear')
            ->once()
            ->with('東茶屋街, 金沢駅', Mockery::type(GeocodedPoint::class))
            ->andReturn(new GeocodedPoint(36.571, 136.671));

        $this->app->instance(NominatimGeocodingService::class, $geocoding);

        $this->actingAs($user)
            ->postJson(route('debug.tourism.search'), [
                'location_name' => '金沢駅',
            ])
            ->assertOk()
            ->assertJsonPath('latestResult.location_name', '金沢駅')
            ->assertJsonPath('latestResult.latitude', 36.578)
            ->assertJsonPath('latestResult.longitude', 136.648)
            ->assertJsonPath('latestResult.status', 'completed')
            ->assertJsonPath('latestResult.spots.0.name', '兼六園')
            ->assertJsonPath('latestResult.spots.0.latitude', 36.562)
            ->assertJsonCount(1, 'recentSearches');

        $this->assertDatabaseHas('tourism_test_searches', [
            'location_name' => '金沢駅',
            'status' => 'completed',
            'latitude' => 36.578,
            'longitude' => 136.648,
        ]);

        $this->assertDatabaseCount('tourism_test_spots', 3);
    }

    public function test_search_validation_requires_location_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('debug.tourism.search'), [
                'location_name' => '',
            ])
            ->assertInvalid(['location_name']);
    }
}
