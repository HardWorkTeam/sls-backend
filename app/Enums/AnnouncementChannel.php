<?php

namespace App\Enums;

enum AnnouncementChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case InApp = 'in_app';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
