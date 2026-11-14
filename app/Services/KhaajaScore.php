<?php

namespace App\Services;

use App\Enums\DeclineReason;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The Kh-aaja score (0–100) ranks shops on the homepage. It rewards a fresh,
 * honest board and good service. The Pro plan never affects it.
 */
class KhaajaScore
{
    /** Maximum points for each part. */
    public const PARTS = [
        'fresh' => 25,
        'consistent' => 20,
        'honest' => 15,
        'quick' => 15,
        'menu' => 10,
        'loved' => 15,
    ];

    public const WINDOW_DAYS = 14;

    /** "Quick replies" points for a shop that hasn't had reservations yet. */
    public const QUICK_NEUTRAL = 8;

    /**
     * @return array<string, int>
     */
    public function parts(Shop $shop): array
    {
        $today = now($shop->timezone)->startOfDay();
        $from = $today->copy()->subDays(self::WINDOW_DAYS - 1)->toDateString();

        $updatedDays = $shop->boardUpdates()
            ->whereBetween('board_date', [$from, $today->toDateString()])
            ->count();

        return [
            'fresh' => $this->fresh($shop, $today),
            'consistent' => (int) round(self::PARTS['consistent'] * min($updatedDays, self::WINDOW_DAYS) / self::WINDOW_DAYS),
            'honest' => $this->honest($shop, $from, $updatedDays),
            'quick' => $this->quick($shop, $from),
            'menu' => $this->menu($shop),
            'loved' => $this->loved($shop),
        ];
    }

    /**
     * @return array{total: int, parts: array<string, int>, tip: string|null}
     */
    public function summary(Shop $shop): array
    {
        $parts = $this->parts($shop);

        return [
            'total' => array_sum($parts),
            'parts' => $parts,
            'tip' => $this->tip($parts),
        ];
    }

    /**
     * Saves the score used for homepage ranking, without touching updated_at.
     */
    public function refresh(Shop $shop): int
    {
        $parts = $this->parts($shop);
        $total = array_sum($parts);

        Shop::query()->whereKey($shop->id)->toBase()->update([
            'score' => $total,
            'score_parts' => json_encode($parts),
            'score_updated_at' => now(),
        ]);

        return $total;
    }

    public static function label(string $part): string
    {
        return match ($part) {
            'fresh' => __('Fresh board'),
            'consistent' => __('Updates every day'),
            'honest' => __('Honest stock'),
            'quick' => __('Quick replies'),
            'menu' => __('Complete menu'),
            'loved' => __('Loved by customers'),
            default => $part,
        };
    }

    /**
     * One tip for the part that is losing the most points.
     *
     * @param  array<string, int>  $parts
     */
    public function tip(array $parts): ?string
    {
        $missing = [];

        foreach (self::PARTS as $part => $max) {
            $missing[$part] = $max - ($parts[$part] ?? 0);
        }

        arsort($missing);
        $worst = array_key_first($missing);

        if ($missing[$worst] <= 0) {
            return null;
        }

        return match ($worst) {
            'fresh' => __('Update your board every morning before customers arrive.'),
            'consistent' => __('Update every day, even when nothing changed. "Copy yesterday" takes one tap.'),
            'honest' => __('Mark dishes sold out as soon as they finish, so nobody reserves them.'),
            'quick' => __('Answer reservations within 10 minutes.'),
            'menu' => __('Add dish photos, a description and your opening hours.'),
            default => __('Put your QR code on the table so customers can ♥ your shop.'),
        };
    }

    private function fresh(Shop $shop, CarbonInterface $today): int
    {
        $last = $shop->boardUpdates()->max('board_date');

        if ($last === null) {
            return 0;
        }

        $days = (int) round(abs($today->diffInDays(Carbon::parse((string) $last, $shop->timezone)->startOfDay())));

        return match (true) {
            $days === 0 => 25,
            $days === 1 => 18,
            $days === 2 => 12,
            $days <= 6 => 5,
            default => 0,
        };
    }

    private function honest(Shop $shop, string $from, int $updatedDays): int
    {
        // A board nobody updates can't earn points for being honest.
        if ($updatedDays === 0) {
            return 0;
        }

        $soldOutDeclines = $shop->reservations()
            ->where('board_date', '>=', $from)
            ->where('status', ReservationStatus::Declined->value)
            ->where('decline_reason', DeclineReason::SoldOut->value)
            ->count();

        return max(0, self::PARTS['honest'] - 5 * $soldOutDeclines);
    }

    private function quick(Shop $shop, string $from): int
    {
        $minutes = $shop->reservations()
            ->where('board_date', '>=', $from)
            ->where('status', '!=', ReservationStatus::Pending->value)
            ->get(['id', 'status', 'created_at', 'decided_at'])
            ->map(fn (Reservation $r): int => $r->status === ReservationStatus::Expired || $r->decided_at === null || $r->created_at === null
                ? 24 * 60
                : (int) abs($r->created_at->diffInMinutes($r->decided_at)))
            ->sort()
            ->values();

        if ($minutes->isEmpty()) {
            return self::QUICK_NEUTRAL;
        }

        $typical = $minutes[intdiv($minutes->count(), 2)]; // the middle value (median)

        return match (true) {
            $typical <= 10 => 15,
            $typical <= 30 => 11,
            $typical <= 60 => 7,
            $typical <= 180 => 3,
            default => 0,
        };
    }

    private function menu(Shop $shop): int
    {
        $items = $shop->items()->where('is_archived', false)->get(['id', 'photo_path']);
        $count = $items->count();

        $points = 5 * min($count, 8) / 8;

        if ($count > 0) {
            $points += 3 * $items->whereNotNull('photo_path')->count() / $count;
        }

        $points += ($shop->description ? 1 : 0) + ($shop->opens_at && $shop->closes_at ? 1 : 0);

        return (int) round($points);
    }

    private function loved(Shop $shop): int
    {
        $hearts = $shop->favourites_count
            + (int) $shop->items()->where('is_archived', false)->sum('favourites_count');

        // Grows slowly: 9 hearts ≈ half, 99 hearts = full.
        return (int) round(self::PARTS['loved'] * min(1, log10(1 + $hearts) / 2));
    }
}
