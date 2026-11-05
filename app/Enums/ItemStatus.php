<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Available = 'available';
    case Low = 'low';
    case SoldOut = 'sold_out';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Low => __('Low'),
            self::SoldOut => __('Sold out'),
        };
    }
}
