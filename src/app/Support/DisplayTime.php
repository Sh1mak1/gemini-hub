<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class DisplayTime
{
    public static function timezone(): string
    {
        return (string) config('app.display_timezone', 'Asia/Tokyo');
    }

    public static function dbTimezone(): string
    {
        return (string) config('app.db_timezone', 'UTC');
    }

    public static function today(): CarbonInterface
    {
        return Carbon::today(self::timezone());
    }

    public static function fromDbDatetime(CarbonInterface|string|null $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->copy()
                ->utc()
                ->timezone(self::timezone());
        }

        return Carbon::parse($value, self::dbTimezone())
            ->timezone(self::timezone());
    }

    public static function fromModelTimestamp(Model $model, string $column): ?CarbonInterface
    {
        $raw = $model->getRawOriginal($column);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return self::fromDbDatetime($raw);
    }
}
