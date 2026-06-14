<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourismTestSearch extends Model
{
    protected $fillable = [
        'location_name',
        'latitude',
        'longitude',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function spots(): HasMany
    {
        return $this->hasMany(TourismTestSpot::class)->orderBy('sort_order');
    }
}
