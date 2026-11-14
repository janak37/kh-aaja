<?php

namespace App\Enums;

enum DeclineReason: string
{
    case SoldOut = 'sold_out';
    case Closed = 'closed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SoldOut => __('Sold out'),
            self::Closed => __('Shop closed'),
            self::Other => __('Other reason'),
        };
    }
}
