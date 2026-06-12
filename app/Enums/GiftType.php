<?php

namespace App\Enums;

enum GiftType: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Item = 'item';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
