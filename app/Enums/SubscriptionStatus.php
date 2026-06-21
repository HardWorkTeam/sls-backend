<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';        // package selected, not yet paid
    case Submitted = 'submitted';    // couple says they paid, awaiting admin confirmation
    case Paid = 'paid';              // admin confirmed payment
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
