<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case Planned = 'planned';
    case Partial = 'partial';
    case Paid = 'paid';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
