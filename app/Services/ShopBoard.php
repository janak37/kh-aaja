<?php

namespace App\Services;

use App\Enums\ItemStatus;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * A shop's board for "today", where today is the shop's local date
 * (Asia/Kathmandu by default), never the server clock.
 */
class ShopBoard
{
    public const MAX_SPECIALS = 3;

    public function __construct(private readonly Shop $shop) {}

    public function date(): string
    {
        return now($this->shop->timezone)->toDateString();
    }

    public function yesterday(): string
    {
        return now($this->shop->timezone)->subDay()->toDateString();
    }

    /**
     * Dishes still on the menu, in menu order.
     *
     * @return Collection<int, Item>
     */
    public function items(): Collection
    {
        return $this->shop->items()->active()->get();
    }

    /**
     * The shop's daily rows for one date, keyed by item_id.
     *
     * @return Collection<int, DailyStatus>
     */
    public function statuses(?string $date = null): Collection
    {
        return DailyStatus::query()
            ->whereIn('item_id', $this->shop->items()->pluck('id'))
            ->where('board_date', $date ?? $this->date())
            ->get()
            ->keyBy('item_id');
    }

    public function lastUpdatedAt(): ?CarbonInterface
    {
        return $this->shop->boardUpdates()
            ->where('board_date', $this->date())
            ->first()
            ?->last_updated_at;
    }

    public function specialsCount(): int
    {
        return $this->statuses()->where('is_special', true)->count();
    }

    public function setStatus(Item $item, ItemStatus $status): DailyStatus
    {
        return $this->save($item, ['status' => $status]);
    }

    public function setPrice(Item $item, ?int $price): DailyStatus
    {
        return $this->save($item, ['price' => $price]);
    }

    /**
     * Star or unstar a dish as today's special.
     *
     * @throws DomainException when a 4th special would be added
     */
    public function toggleSpecial(Item $item): DailyStatus
    {
        $makeSpecial = ! ($this->statuses()->get($item->id)->is_special ?? false);

        if ($makeSpecial && $this->specialsCount() >= self::MAX_SPECIALS) {
            throw new DomainException(__('You can star up to :max specials a day.', ['max' => self::MAX_SPECIALS]));
        }

        return $this->save($item, ['is_special' => $makeSpecial]);
    }

    /**
     * Copy yesterday's status, price and specials onto today.
     * Returns how many dishes were copied.
     */
    public function copyYesterday(): int
    {
        $yesterday = $this->statuses($this->yesterday());
        $copied = 0;

        foreach ($this->items() as $item) {
            $previous = $yesterday->get($item->id);

            if ($previous === null) {
                continue;
            }

            $this->save($item, [
                'status' => $previous->status,
                'price' => $previous->price,
                'is_special' => $previous->is_special,
                'special_note' => $previous->special_note,
            ]);

            $copied++;
        }

        return $copied;
    }

    /**
     * Mark every dish on the menu as available for today.
     */
    public function resetAllToAvailable(): int
    {
        $items = $this->items();

        foreach ($items as $item) {
            $this->save($item, ['status' => ItemStatus::Available]);
        }

        return $items->count();
    }

    /**
     * Create or update today's row for one dish, then record that the
     * board was updated today (this drives the streak and the score).
     *
     * @param  array<string, mixed>  $values
     */
    private function save(Item $item, array $values): DailyStatus
    {
        abort_unless($item->shop_id === $this->shop->id && ! $item->is_archived, 404);

        /** @var DailyStatus $row */
        $row = $item->dailyStatuses()->firstOrNew(['board_date' => $this->date()]);
        $row->fill($values);

        if (! $row->exists && ! array_key_exists('status', $values)) {
            $row->status = ItemStatus::Available;
        }

        $row->save();

        $this->shop->boardUpdates()->updateOrCreate(
            ['board_date' => $this->date()],
            ['last_updated_at' => now()],
        );

        return $row;
    }
}
