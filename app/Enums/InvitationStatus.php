<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
