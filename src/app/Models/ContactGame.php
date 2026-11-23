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
        'character_role',
        'channel',
        'clear_condition',
        'resource_name',
        'resource_remaining',
        'resource_max',
        'resource_rule',
        'last_change_note',
        'loss_reason',
        'ending',
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
            'character_role' => $this->character_role,
            'channel' => $this->channel,
            'clear_condition' => $this->clear_condition,
            'resource_name' => $this->resource_name,
            'resource_remaining' => $this->resource_remaining,
            'resource_max' => $this->resource_max,
            'resource_rule' => $this->resource_rule,
            'last_change_note' => $this->last_change_note,
            'loss_reason' => $this->loss_reason,
            'ending' => $this->ending,
            'messages' => array_values($this->messages ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDebugArray(): array
    {
        $hidden = $this->hidden_state ?? [];
        $interventions = $hidden['useful_interventions'] ?? [];

        if (! is_array($interventions)) {
            $interventions = [];
        }

        return [
            'progress' => (int) ($hidden['progress'] ?? 0),
            'misconception' => is_string($hidden['misconception'] ?? null) ? $hidden['misconception'] : '',
            'useful_interventions' => array_values(array_filter(
                $interventions,
                fn ($item) => is_string($item) && $item !== '',
            )),
            'doom_if_ignored' => is_string($hidden['doom_if_ignored'] ?? null) ? $hidden['doom_if_ignored'] : '',
        ];
    }

    public function setDebugResource(int $remaining): void
    {
        $remaining = max(0, min(99, $remaining));

        $this->resource_remaining = $remaining;

        if ($remaining > $this->resource_max) {
            $this->resource_max = $remaining;
        }

        if ($this->status === 'lost' && $remaining > 0) {
            $this->status = 'ongoing';
            $this->loss_reason = null;
        }

        $this->save();
    }
}
