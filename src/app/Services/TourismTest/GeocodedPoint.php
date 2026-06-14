<?php

namespace App\Services\TourismTest;

readonly class GeocodedPoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}
}
