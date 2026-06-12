<?php

namespace App\Enums;

enum WeddingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
