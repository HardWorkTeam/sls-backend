<?php

namespace App\Enums;

enum MediaType: string
{
    case Photo = 'photo';
    case Video = 'video';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
