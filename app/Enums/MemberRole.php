<?php

namespace App\Enums;

enum MemberRole: string
{
    case Bride = 'bride';
    case Groom = 'groom';
    case Member = 'member';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
