<?php

namespace App\Enums;

enum GuestGroupType: string
{
    case Family = 'family';
    case Friends = 'friends';
    case Vip = 'vip';
    case Company = 'company';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
