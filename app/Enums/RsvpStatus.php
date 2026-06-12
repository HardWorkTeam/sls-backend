<?php

namespace App\Enums;

enum RsvpStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Maybe = 'maybe';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses a guest may submit from the public RSVP form.
     *
     * @return list<string>
     */
    public static function submittable(): array
    {
        return [self::Accepted->value, self::Declined->value, self::Maybe->value];
    }
}
