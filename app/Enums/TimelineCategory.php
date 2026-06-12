<?php

namespace App\Enums;

enum TimelineCategory: string
{
    case Engagement = 'engagement';
    case Ceremony = 'ceremony';
    case Reception = 'reception';
    case AfterParty = 'after_party';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
