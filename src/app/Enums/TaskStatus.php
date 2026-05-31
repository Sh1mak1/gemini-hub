<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '未完了',
            self::Completed => '完了',
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }
}
