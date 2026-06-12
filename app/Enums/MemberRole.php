<?php

namespace App\Enums;

enum MemberRole: string
{
    case Bride = 'bride';
    case Groom = 'groom';
    case Organizer = 'organizer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
