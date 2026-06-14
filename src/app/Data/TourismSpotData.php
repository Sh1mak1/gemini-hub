<?php

namespace App\Data;

readonly class TourismSpotData
{
    public function __construct(
        public string $name,
        public ?float $distanceKm,
        public string $distanceText,
        public string $description,
        public ?string $imageUrl = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $distanceKm = $data['distance_km'] ?? null;

        return new self(
            name: is_string($data['name'] ?? null) ? trim($data['name']) : '',
            distanceKm: is_numeric($distanceKm) ? (float) $distanceKm : null,
            distanceText: is_string($data['distance_text'] ?? null) ? trim($data['distance_text']) : '',
            description: is_string($data['description'] ?? null) ? trim($data['description']) : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toSpotAttributes(int $searchId, int $sortOrder): array
    {
        return [
            'tourism_test_search_id' => $searchId,
            'sort_order' => $sortOrder,
            'name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_km' => $this->distanceKm,
            'distance_text' => $this->distanceText,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'name' => $this->name,
            'distance_km' => $this->distanceKm,
            'distance_text' => $this->distanceText,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
