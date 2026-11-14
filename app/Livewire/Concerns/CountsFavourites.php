<?php

namespace App\Livewire\Concerns;

use App\Services\FavouriteCounter;

/**
 * Lets a public page receive ♥ taps. What a customer saved lives on their device;
 * the server only keeps the anonymous count.
 */
trait CountsFavourites
{
    public function favourite(string $type, int $id, bool $on): void
    {
        FavouriteCounter::forIp(request()->ip())->record($type, $id, $on);
    }
}
