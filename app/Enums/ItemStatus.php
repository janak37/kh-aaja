<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Available = 'available';
    case Low = 'low';
    case SoldOut = 'sold_out';
}
