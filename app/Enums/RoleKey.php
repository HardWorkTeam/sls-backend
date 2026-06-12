<?php

namespace App\Enums;

enum RoleKey: string
{
    case SuperAdmin = 'super_admin';
    case Organizer = 'organizer';
    case Couple = 'couple';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
