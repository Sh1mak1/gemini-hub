<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourismTestSpot extends Model
{
    protected $fillable = [
        'tourism_test_search_id',
        'sort_order',
        'name',
        'distance_km',
        'distance_text',
        'description',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'float',
        ];
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(TourismTestSearch::class, 'tourism_test_search_id');
    }
}
