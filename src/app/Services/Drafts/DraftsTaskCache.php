<?php

namespace App\Services\Drafts;

use App\Data\DraftsTaskSnapshot;
use Illuminate\Support\Facades\Cache;

class DraftsTaskCache
{
    private const string CACHE_KEY = 'drafts.task_snapshot';

    public function store(DraftsTaskSnapshot $snapshot): void
    {
        Cache::put(
            self::CACHE_KEY,
            [
                'text' => $snapshot->text,
                'lines' => $snapshot->lines,
            ],
            now()->addMinutes((int) config('drafts.cache_ttl_minutes')),
        );
    }

    public function get(): ?DraftsTaskSnapshot
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (! is_array($cached)) {
            return null;
        }

        $text = $cached['text'] ?? null;
        $lines = $cached['lines'] ?? null;

        if (! is_string($text) || ! is_array($lines)) {
            return null;
        }

        return new DraftsTaskSnapshot($text, $lines);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
