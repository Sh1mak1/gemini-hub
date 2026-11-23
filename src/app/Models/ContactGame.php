<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactGame extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'sentence',
        'situation',
        'character_name',
        'channel',
        'clear_condition',
        'resource_name',
        'resource_remaining',
        'resource_max',
        'resource_rule',
        'last_change_note',
        'loss_reason',
        'hidden_state',
        'messages',
    ];

    protected function casts(): array
    {
        return [
            'resource_remaining' => 'integer',
            'resource_max' => 'integer',
            'hidden_state' => 'array',
            'messages' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'sentence' => $this->sentence,
            'situation' => $this->situation,
            'character_name' => $this->character_name,
            'channel' => $this->channel,
            'clear_condition' => $this->clear_condition,
            'resource_name' => $this->resource_name,
            'resource_remaining' => $this->resource_remaining,
            'resource_max' => $this->resource_max,
            'resource_rule' => $this->resource_rule,
            'last_change_note' => $this->last_change_note,
            'loss_reason' => $this->loss_reason,
            'messages' => array_values($this->messages ?? []),
        ];
    }
}
