<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftsTaskQueue extends Model
{
    protected $table = 'drafts_task_queue';

    protected $fillable = [
        'input_text',
        'attempts',
        'last_error',
        'last_attempted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_attempted_at' => 'datetime',
        ];
    }
}
