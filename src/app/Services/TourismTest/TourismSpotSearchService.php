<?php

namespace App\Services\TourismTest;

use App\Data\TourismSpotData;
use App\Models\TourismTestSearch;
use App\Services\Gemini\GeminiClient;
use App\Support\OperationLogger;
use RuntimeException;
use Throwable;

class TourismSpotSearchService
{
    private const EXPECTED_SPOT_COUNT = 3;

    public function __construct(
        private GeminiClient $gemini,
        private WikipediaImageResolver $imageResolver,
        private NominatimGeocodingService $geocoding,
    ) {}

    /**
     * @return array{
     *     search: TourismTestSearch,
     *     spots: list<TourismSpotData>
     * }
     */
    public function search(string $locationName): array
    {
        $locationName = trim($locationName);

        if ($locationName === '') {
            throw new RuntimeException('土地名を入力してください。');
        }

        OperationLogger::info('tourism.test', 'search_started', [
            'location_name' => $locationName,
        ]);

        $search = TourismTestSearch::query()->create([
            'location_name' => $locationName,
            'status' => 'failed',
        ]);

        try {
            $center = $this->geocoding->geocode($locationName);

            if ($center === null) {
                throw new RuntimeException('入力地点の位置を特定できませんでした。');
            }

            $search->update([
                'latitude' => $center->latitude,
                'longitude' => $center->longitude,
            ]);

            $spots = $this->fetchSpots($locationName);
            $spotsWithImages = $this->attachImages($spots);
            $spotsWithCoordinates = $this->attachCoordinates($spotsWithImages, $locationName, $center);

            foreach ($spotsWithCoordinates as $index => $spot) {
                $search->spots()->create($spot->toSpotAttributes($search->id, $index + 1));
            }

            $search->update([
                'status' => 'completed',
                'error_message' => null,
            ]);

            OperationLogger::info('tourism.test', 'search_completed', [
                'search_id' => $search->id,
                'location_name' => $locationName,
                'spot_names' => array_map(fn (TourismSpotData $spot) => $spot->name, $spotsWithCoordinates),
                'geocoded_spots' => count(array_filter(
                    $spotsWithCoordinates,
                    fn (TourismSpotData $spot) => $spot->latitude !== null && $spot->longitude !== null,
                )),
            ]);

            return [
                'search' => $search->fresh(['spots']),
                'spots' => $spotsWithCoordinates,
            ];
        } catch (Throwable $exception) {
            $search->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            OperationLogger::error('tourism.test', 'search_failed', [
                'search_id' => $search->id,
                'location_name' => $locationName,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return list<TourismSpotData>
     */
    private function fetchSpots(string $locationName): array
    {
        $result = $this->gemini->generateStructured(
            model: (string) config('gemini.models.flash'),
            prompt: $this->buildPrompt($locationName),
            responseSchema: $this->responseSchema(),
        );

        $rawSpots = $result['spots'] ?? null;

        if (! is_array($rawSpots)) {
            throw new RuntimeException('観光スポット情報の取得に失敗しました。');
        }

        $spots = [];

        foreach ($rawSpots as $rawSpot) {
            if (! is_array($rawSpot)) {
                continue;
            }

            $spot = TourismSpotData::fromArray($rawSpot);

            if ($spot->name === '' || $spot->description === '') {
                continue;
            }

            $spots[] = $spot;
        }

        if (count($spots) !== self::EXPECTED_SPOT_COUNT) {
            throw new RuntimeException('観光スポットを 3 件取得できませんでした。');
        }

        return $spots;
    }

    /**
     * @param  list<TourismSpotData>  $spots
     * @return list<TourismSpotData>
     */
    private function attachImages(array $spots): array
    {
        return array_map(function (TourismSpotData $spot): TourismSpotData {
            $imageUrl = $this->imageResolver->resolve($spot->name);

            return new TourismSpotData(
                name: $spot->name,
                distanceKm: $spot->distanceKm,
                distanceText: $spot->distanceText,
                description: $spot->description,
                imageUrl: $imageUrl,
            );
        }, $spots);
    }

    /**
     * @param  list<TourismSpotData>  $spots
     * @return list<TourismSpotData>
     */
    private function attachCoordinates(array $spots, string $locationName, GeocodedPoint $center): array
    {
        return array_map(function (TourismSpotData $spot) use ($locationName, $center): TourismSpotData {
            $coordinates = $this->geocoding->geocodeNear(
                $spot->name,
                $center,
                $spot->distanceKm,
            );

            return new TourismSpotData(
                name: $spot->name,
                distanceKm: $spot->distanceKm,
                distanceText: $spot->distanceText,
                description: $spot->description,
                imageUrl: $spot->imageUrl,
                latitude: $coordinates?->latitude,
                longitude: $coordinates?->longitude,
            );
        }, $spots);
    }

    private function buildPrompt(string $locationName): string
    {
        return <<<PROMPT
あなたは日本の観光案内アシスタントです。
基準地点「{$locationName}」の近辺（おおよそ 30km 圏内）にある、実在する観光スポットを 3 件選んでください。

要件:
- 架空のスポット名は使わない
- 各スポットは基準地点からの直線距離の概算を km 単位で示す
- distance_text は日本語の表示用（例: 約 2.5km）
- description は 2〜4 文の日本語で、そのスポットの魅力を簡潔に説明する
- 必ず spots 配列に 3 件入れる
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'spots' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'distance_km' => ['type' => 'number'],
                            'distance_text' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                        'required' => ['name', 'distance_km', 'distance_text', 'description'],
                    ],
                ],
            ],
            'required' => ['spots'],
        ];
    }
}
