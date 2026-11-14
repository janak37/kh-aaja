<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Counts hearts without accounts. A visitor is a keyed hash of their IP address
 * (the IP itself is never stored), and each visitor adds at most one heart per
 * shop or dish.
 */
class FavouriteCounter
{
    public const MAX_CHANGES_PER_MINUTE = 30;

    private const REMEMBER_DAYS = 365;

    public function __construct(private readonly string $visitor) {}

    public static function forIp(?string $ip): self
    {
        return new self(hash_hmac('sha256', (string) $ip, (string) config('app.key')));
    }

    /**
     * @return bool whether the count changed
     */
    public function record(string $type, int $id, bool $on): bool
    {
        $exists = match ($type) {
            'shop' => Shop::query()->whereKey($id)->whereNull('suspended_at')->exists(),
            'dish' => Item::query()->whereKey($id)->where('is_archived', false)
                ->whereHas('shop', fn ($q) => $q->whereNull('suspended_at'))
                ->exists(),
            default => false,
        };

        if (! $exists) {
            return false;
        }

        $limiter = 'favourite:'.$this->visitor;

        if (RateLimiter::tooManyAttempts($limiter, self::MAX_CHANGES_PER_MINUTE)) {
            return false;
        }

        RateLimiter::hit($limiter, 60);

        $seen = "favourite:{$type}:{$id}:{$this->visitor}";
        $row = DB::table($type === 'shop' ? 'shops' : 'items')->where('id', $id);

        if ($on) {
            // add() only succeeds the first time, so a second ♥ from the same visitor is ignored.
            if (! Cache::add($seen, true, now()->addDays(self::REMEMBER_DAYS))) {
                return false;
            }

            $row->increment('favourites_count');

            return true;
        }

        // Only take back a heart this visitor actually gave.
        if (Cache::pull($seen) === null) {
            return false;
        }

        $row->where('favourites_count', '>', 0)->decrement('favourites_count');

        return true;
    }
}
