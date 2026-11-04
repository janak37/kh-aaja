<?php

namespace App\Enums;

enum UserRole: string
{
    case ShopOwner = 'shop_owner';
    case Admin = 'admin';
}
