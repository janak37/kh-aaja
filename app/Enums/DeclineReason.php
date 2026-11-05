<?php

namespace App\Enums;

enum DeclineReason: string
{
    case SoldOut = 'sold_out';
    case Closed = 'closed';
    case Other = 'other';
}
