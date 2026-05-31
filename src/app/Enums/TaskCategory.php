<?php

namespace App\Enums;

enum TaskCategory: string
{
    case Work = 'work';
    case Hobby = 'hobby';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Work => '仕事',
            self::Hobby => '趣味',
            self::Other => 'その他',
        };
    }
}
