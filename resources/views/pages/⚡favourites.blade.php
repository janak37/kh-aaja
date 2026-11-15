<?php

use App\Enums\ItemStatus;
use App\Livewire\Concerns\CountsFavourites;
use App\Models\Item;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public')] #[Title('Favourites')] class extends Component {
    use CountsFavourites;

    private const MAX = 50;

    /** @var list<int> */
    #[Locked]
    public array $shopIds = [];

    /** @var list<string> */
    #[Locked]
    public array $dishKeys = [];

    #[Locked]
    public bool $loaded = false;

    /**
     * The browser sends what is saved on this device. Anything odd is dropped.
     *
     * @param  array<mixed>  $shopIds
     * @param  array<mixed>  $dishKeys
     */
    public function load(array $shopIds, array $dishKeys): void
    {
        $ids = [];
        foreach ($shopIds as $id) {
            if (count($ids) < self::MAX && is_int($id) && $id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        $keys = [];
        foreach ($dishKeys as $key) {
            if (count($keys) < self::MAX && is_string($key) && $key !== '' && mb_strlen($key) <= 191 && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        $this->shopIds = $ids;
        $this->dishKeys = $keys;
        $this->loaded = true;

        unset($this->shops, $this->dishes, $this->missingDishes);
    }

    /**
     * @return Collection<int, Shop>
     */
    #[Computed]
    public function shops(): Collection
    {
        if ($this->shopIds === []) {
            return new Collection;
        }

        $today = $this->today();

        return Shop::query()
            ->whereIn('id', $this->shopIds)
            ->whereNull('suspended_at')
            ->withExists(['boardUpdates as updated_today' => fn ($q) => $q->where('board_date', $today)])
            ->orderBy('name')
            ->get();
    }

    /**
     * Every shop that has a saved dish available today, cheapest first.
     *
     * @return Collection<int, Item>
     */
    #[Computed]
    public function dishes(): Collection
    {
        if ($this->dishKeys === []) {
            return new Collection;
        }

        $today = $this->today();

        return Item::query()
            ->where('is_archived', false)
            ->whereIn('dish_key', $this->dishKeys)
            ->whereHas('shop', fn ($q) => $q->whereNull('suspended_at')
                ->whereHas('boardUpdates', fn ($q) => $q->where('board_date', $today)))
            ->whereDoesntHave('dailyStatuses', fn ($q) => $q->where('board_date', $today)
                ->where('status', ItemStatus::SoldOut->value))
            ->with(['shop', 'dailyStatuses' => fn ($q) => $q->where('board_date', $today)])
            ->get()
            ->sortBy(fn (Item $item) => $item->dailyStatuses->first()?->price ?? $item->default_price)
            ->values();
    }

    /**
     * How many saved dishes no shop has today.
     */
    #[Computed]
    public function missingDishes(): int
    {
        return count(array_diff($this->dishKeys, $this->dishes->pluck('dish_key')->all()));
    }

    private function today(): string
    {
        return now('Asia/Kathmandu')->toDateString();
    }
}; ?>

<div class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 sm:py-12" x-data x-init="$wire.load($store.fav.shops, $store.fav.dishKeys())">
    <a href="{{ route('home') }}" class="text-sm font-medium text-stone-500 hover:text-orange-700">← {{ __('All shops') }}</a>

    <div class="mt-4 flex items-center gap-3">
        <span class="flex size-11 items-center justify-center rounded-xl bg-orange-100 text-orange-600">
            <flux:icon.heart variant="solid" class="size-6" />
        </span>
        <div>
            <h1 class="text-3xl font-bold tracking-tight">{{ __('Favourites') }}</h1>
            <p class="text-sm text-stone-500">{{ __('Saved on this phone only. No account needed.') }}</p>
        </div>
    </div>

    <div class="mt-8 space-y-10">
        @if (! $loaded)
            <p class="text-sm text-stone-500">{{ __('Loading…') }}</p>
        @elseif ($shopIds === [] && $dishKeys === [])
            <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center">
                <flux:icon.heart class="mx-auto size-10 text-orange-300" />
                <p class="mt-3 text-lg font-semibold">{{ __('Nothing saved yet.') }}</p>
                <p class="mt-1 text-stone-500">{{ __('Tap the ♥ on a shop or a dish to keep it here.') }}</p>
                <a href="{{ route('home') }}" class="mt-5 inline-block rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700">
                    {{ __('Browse shops') }}
                </a>
            </div>
        @else
            {{-- Saved dishes: where to get each one today --}}
            @if ($dishKeys !== [])
                <section>
                    <h2 class="text-xl font-semibold">{{ __('Your dishes, available today') }}</h2>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach ($this->dishes->groupBy('dish_key') as $key => $group)
                            <div wire:key="dish-{{ $key }}" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
                                <div class="flex items-center justify-between gap-3 border-b border-orange-100 bg-orange-50 px-4 py-3">
                                    <div>
                                        <span class="font-semibold">{{ $group->first()->name }}</span>
                                        <span class="text-sm text-stone-500">· {{ trans_choice(':count shop today|:count shops today', $group->count()) }}</span>
                                    </div>
                                    <x-fav-heart :item="$group->first()" />
                                </div>

                                <div class="divide-y divide-stone-100">
                                    @foreach ($group as $item)
                                        @php $price = $item->dailyStatuses->first()?->price ?? $item->default_price; @endphp
                                        <a href="{{ route('board.show', $item->shop) }}" wire:key="dish-item-{{ $item->id }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50">
                                            <span class="min-w-0 flex-1 truncate text-sm">
                                                {{ $item->shop->name }} · <span class="text-stone-500">{{ $item->shop->area }}</span>
                                            </span>
                                            @if ($loop->first && $group->count() > 1)
                                                <flux:badge color="green" size="sm">{{ __('Cheapest') }}</flux:badge>
                                            @endif
                                            @if ($price)
                                                <span class="shrink-0 text-sm font-semibold text-orange-700">Rs {{ number_format($price) }}</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($this->missingDishes > 0)
                        <p class="mt-3 text-sm text-stone-500">
                            {{ trans_choice(':count saved dish is not on any board today.|:count saved dishes are not on any board today.', $this->missingDishes) }}
                        </p>
                    @endif
                </section>
            @endif

            {{-- Saved shops --}}
            @if ($shopIds !== [])
                <section>
                    <h2 class="text-xl font-semibold">{{ __('Your shops') }}</h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($this->shops as $shop)
                            <div wire:key="shop-{{ $shop->id }}" class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200 transition hover:shadow-md">
                                <div class="flex items-start justify-between gap-3">
                                    <a href="{{ route('board.show', $shop) }}" class="min-w-0">
                                        <div class="truncate text-lg font-semibold">{{ $shop->name }}</div>
                                        <div class="truncate text-sm text-stone-500">{{ $shop->categoryLabel() }} · {{ $shop->area }}</div>
                                    </a>
                                    <x-fav-heart :shop="$shop" />
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3">
                                    @if ($shop->updated_today)
                                        <flux:badge color="green" size="sm">{{ __('Board updated today') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ __('Not updated today') }}</flux:badge>
                                    @endif
                                    <a href="{{ route('board.show', $shop) }}" class="text-sm font-semibold text-orange-700 hover:text-orange-800">
                                        {{ __('View board') }} →
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </div>
</div>