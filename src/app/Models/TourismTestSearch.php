<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourismTestSearch extends Model
{
    protected $fillable = [
        'location_name',
        'status',
        'error_message',
    ];

    public function spots(): HasMany
    {
        return $this->hasMany(TourismTestSpot::class)->orderBy('sort_order');
    }
}
