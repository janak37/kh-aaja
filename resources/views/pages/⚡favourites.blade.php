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

<div class="mx-auto min-h-screen w-full max-w-3xl space-y-8 px-4 pb-16 pt-5" x-data x-init="$wire.load($store.fav.shops, $store.fav.dishKeys())">
    <header class="flex items-center justify-between gap-3">
        <a href="{{ route('home') }}" class="text-lg font-bold">
            Kh-aaja <span class="font-normal text-zinc-500">· खाजा आज</span>
        </a>
        <x-language-switch />
    </header>
    <div>
        <h1 class="text-2xl font-bold">{{ __('Favourites') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Saved on this phone only. No account needed.') }}</p>
    </div>

    @if (! $loaded)
        <p class="text-sm text-zinc-500">{{ __('Loading…') }}</p>
    @elseif ($shopIds === [] && $dishKeys === [])
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
            <flux:icon.heart class="mx-auto size-8 text-zinc-400" />
            <p class="mt-3 font-semibold">{{ __('Nothing saved yet.') }}</p>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Tap the ♥ on a shop or a dish to keep it here.') }}</p>
            <a href="{{ route('home') }}" class="mt-4 inline-block text-sm font-semibold underline">{{ __('Browse shops') }}</a>
        </div>
    @else
        @if ($dishKeys !== [])
            <section class="space-y-3">
                <h2 class="text-lg font-semibold">{{ __('Your dishes, available today') }}</h2>

                @foreach ($this->dishes->groupBy('dish_key') as $key => $group)
                    <div wire:key="dish-{{ $key }}" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center justify-between gap-3 bg-zinc-50 px-3 py-2 dark:bg-zinc-800">
                            <div>
                                <span class="font-semibold">{{ $group->first()->name }}</span>
                                <span class="text-sm text-zinc-500">· {{ trans_choice(':count shop today|:count shops today', $group->count()) }}</span>
                            </div>
                            <x-fav-heart :item="$group->first()" />
                        </div>

                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($group as $item)
                                @php $price = $item->dailyStatuses->first()?->price ?? $item->default_price; @endphp
                                <a href="{{ route('board.show', $item->shop) }}" wire:key="dish-item-{{ $item->id }}" class="flex items-center gap-3 px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <span class="min-w-0 flex-1 truncate text-sm">{{ $item->shop->name }} · <span class="text-zinc-500">{{ $item->shop->area }}</span></span>
                                    @if ($loop->first && $group->count() > 1)
                                        <flux:badge color="green" size="sm">{{ __('Cheapest') }}</flux:badge>
                                    @endif
                                    @if ($price)
                                        <span class="shrink-0 text-sm font-semibold">Rs {{ number_format($price) }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($this->missingDishes > 0)
                    <p class="text-sm text-zinc-500">
                        {{ trans_choice(':count saved dish is not on any board today.|:count saved dishes are not on any board today.', $this->missingDishes) }}
                    </p>
                @endif
            </section>
        @endif

        @if ($shopIds !== [])
            <section class="space-y-3">
                <h2 class="text-lg font-semibold">{{ __('Your shops') }}</h2>

                @foreach ($this->shops as $shop)
                    <div wire:key="shop-{{ $shop->id }}" class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <a href="{{ route('board.show', $shop) }}" class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $shop->name }}</div>
                            <div class="truncate text-sm text-zinc-500">{{ $shop->area }} · {{ $shop->categoryLabel() }}</div>
                        </a>
                        @if ($shop->updated_today)
                            <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">{{ __('Board updated today') }}</span>
                        @else
                            <span class="rounded-full bg-zinc-200 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ __('Not updated today') }}</span>
                        @endif
                        <x-fav-heart :shop="$shop" />
                    </div>
                @endforeach
            </section>
        @endif
    @endif
</div>