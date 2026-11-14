<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Collected = 'collected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for the shop'),
            self::Confirmed => __('Confirmed'),
            self::Declined => __('Declined'),
            self::Collected => __('Collected'),
            self::Expired => __('Expired'),
        };
    }
}
