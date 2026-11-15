<?php

use App\Enums\ItemStatus;
use App\Livewire\Concerns\CountsFavourites;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::public')] #[Title('Kh-aaja')] class extends Component {
    use CountsFavourites;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $area = '';

    #[Url(except: '')]
    public string $category = '';

    public function toggleCategory(string $category): void
    {
        $this->category = $this->category === $category ? '' : $category;
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'area', 'category');
    }

    /**
     * Today's specials from every active shop that matches the filters.
     * Sold-out specials are left out of the homepage.
     *
     * @return Collection<int, DailyStatus>
     */
    #[Computed]
    public function specials(): Collection
    {
        return DailyStatus::query()
            ->where('board_date', $this->today())
            ->where('is_special', true)
            ->where('status', '!=', ItemStatus::SoldOut)
            ->whereHas('item', fn ($item) => $item
                ->where('is_archived', false)
                ->whereHas('shop', fn ($shop) => $this->applyShopFilters($shop)))
            ->with('item.shop')
            ->latest('updated_at')
            ->limit(12)
            ->get();
    }

    /**
     * Shops updated today first, then by score, then most recently updated.
     *
     * @return Collection<int, Shop>
     */
    #[Computed]
    public function shops(): Collection
    {
        $today = $this->today();
        $query = Shop::query();

        $this->applyShopFilters($query);

        return $query
            ->withExists(['boardUpdates as updated_today' => fn ($q) => $q->where('board_date', $today)])
            ->withMax('boardUpdates as last_board_date', 'board_date')
            ->with(['items' => fn ($q) => $q
                ->where('is_archived', false)
                ->with(['dailyStatuses' => fn ($d) => $d->where('board_date', $today)])])
            ->orderByDesc('updated_today')
            ->orderByDesc('score')
            ->orderByDesc('last_board_date')
            ->limit(30)
            ->get();
    }

    /**
     * Filters shared by the specials row and the shop list.
     * Area and category from the URL are only used if they are real values.
     *
     * @param  Builder<Shop>  $query
     */
    private function applyShopFilters(Builder $query): void
    {
        $search = trim($this->q);

        $query->whereNull('suspended_at')
            ->when(in_array($this->area, config('khaaja.areas'), true), fn ($q) => $q->where('area', $this->area))
            ->when(array_key_exists($this->category, config('khaaja.categories')), fn ($q) => $q->where('category', $this->category))
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';

                $q->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhereHas('items', fn ($i) => $i->where('is_archived', false)->where('name', 'like', $term)));
            });
    }

    /**
     * v1: every shop is in Kathmandu, so one "today" for the whole homepage.
     */
    private function today(): string
    {
        return now('Asia/Kathmandu')->toDateString();
    }
}; ?>

<div wire:poll.30s.visible class="pb-12">
    {{-- Hero: question, search and filters --}}
    <section class="border-b border-orange-100 bg-gradient-to-b from-orange-50 to-stone-50">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <h1 class="max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">{{ __('What can you eat nearby right now?') }}</h1>
            <p class="mt-2 max-w-2xl text-stone-600">{{ __('Live boards from local khaja shops, updated today.') }}</p>

            <div class="mt-6 flex flex-col gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200 sm:flex-row">
                <div class="flex-1">
                    <flux:input wire:model.live.debounce.300ms="q" icon="magnifying-glass" :placeholder="__('Search momo, thakali, shop…')" />
                </div>
                <div class="sm:w-56">
                    <flux:select wire:model.live="area">
                        <flux:select.option value="">{{ __('All areas') }}</flux:select.option>
                        @foreach (config('khaaja.areas') as $areaName)
                            <flux:select.option :value="$areaName">{{ $areaName }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                <button type="button" wire:click="$set('category', '')" @class([
                    'shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition',
                    'border-orange-600 bg-orange-600 text-white' => $category === '',
                    'border-stone-200 bg-white text-stone-700 hover:border-orange-300' => $category !== '',
                ])>{{ __('All') }}</button>

                @foreach (config('khaaja.categories') as $value => $label)
                    <button type="button" wire:click="toggleCategory('{{ $value }}')" @class([
                        'shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition',
                        'border-orange-600 bg-orange-600 text-white' => $category === $value,
                        'border-stone-200 bg-white text-stone-700 hover:border-orange-300' => $category !== $value,
                    ])>{{ __($label) }}</button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Today's specials: a swipe row on phones, a grid on bigger screens --}}
    @if ($this->specials->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-10 sm:px-6">
            <h2 class="text-xl font-semibold">⭐ {{ __("Today's specials") }}</h2>

            <div class="-mx-4 mt-4 flex snap-x gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 lg:grid-cols-4">
                @foreach ($this->specials as $special)
                    @php
                        $item = $special->item;
                        $shop = $item->shop;
                        $price = $special->price ?? $item->default_price;
                    @endphp

                    <div wire:key="special-{{ $special->id }}" class="relative w-56 shrink-0 snap-start overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:shadow-md sm:w-auto">
                        <a href="{{ route('board.show', $shop) }}" class="block">
                            @if ($item->photoUrl())
                                <img src="{{ $item->photoUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-36 w-full object-cover">
                            @else
                                <div class="flex h-24 items-center justify-center bg-gradient-to-br from-orange-50 to-amber-100 text-4xl">
                                    🍽️
                                </div>
                            @endif

                            <div class="space-y-1.5 p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-semibold leading-tight">{{ $item->name }}</span>
                                    @if ($price)
                                        <span class="shrink-0 text-sm font-semibold text-orange-700">Rs {{ number_format($price) }}</span>
                                    @endif
                                </div>
                                <div class="truncate text-xs text-stone-500">{{ $shop->name }} · {{ $shop->area }}</div>
                                <x-status-pill :status="$special->status" />
                            </div>
                        </a>

                        <x-fav-heart :item="$item" class="absolute right-2 top-2 bg-white/90 shadow-sm" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Shops: 1 column on phones, 2 on tablets, 3 on laptops --}}
    <section class="mx-auto max-w-6xl px-4 pt-10 sm:px-6">
        <div class="flex items-end justify-between gap-3">
            <h2 class="text-xl font-semibold">{{ __('Shops') }}</h2>
            <span class="text-sm text-stone-500">{{ trans_choice(':count shop|:count shops', $this->shops->count()) }}</span>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->shops as $shop)
                @php
                    $ready = $shop->items->filter(fn (Item $item) => in_array(
                        $item->dailyStatuses->first()?->status,
                        [ItemStatus::Available, ItemStatus::Low],
                        true,
                    ));
                @endphp

                <div wire:key="shop-{{ $shop->id }}" @class([
                    'flex flex-col rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                    'opacity-75' => ! $shop->updated_today,
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('board.show', $shop) }}" class="min-w-0">
                            <h3 class="truncate text-lg font-semibold">{{ $shop->name }}</h3>
                            <p class="text-sm text-stone-500">{{ $shop->categoryLabel() }} · {{ $shop->area }}</p>
                        </a>

                        <div class="flex shrink-0 items-center text-sm text-stone-500">
                            @if ($shop->favourites_count > 0)
                                <span>{{ $shop->favourites_count }}</span>
                            @endif
                            <x-fav-heart :shop="$shop" />
                        </div>
                    </div>

                    <div class="mt-3">
                        @if ($shop->updated_today)
                            <flux:badge color="green" size="sm">
                                {{ trans_choice(':count dish ready|:count dishes ready', $ready->count()) }}
                            </flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Not updated today') }}</flux:badge>
                        @endif
                    </div>

                    @if ($ready->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($ready->take(4) as $item)
                                <span class="rounded-full bg-orange-50 px-2.5 py-0.5 text-xs text-orange-800">{{ $item->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    <a href="{{ route('board.show', $shop) }}" class="mt-auto pt-4 text-sm font-semibold text-orange-700 hover:text-orange-800">
                        {{ __('View board') }} →
                    </a>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-8 text-center sm:col-span-2 lg:col-span-3">
                    <p class="text-stone-600">{{ __('No shops match your search.') }}</p>
                    <flux:button size="sm" class="mt-3" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                </div>
            @endforelse
        </div>
    </section>

    {{-- For shop owners --}}
    <section class="mx-auto max-w-6xl px-4 pt-12 sm:px-6">
        <div class="flex flex-col items-start justify-between gap-4 rounded-2xl bg-orange-600 p-6 text-white sm:flex-row sm:items-center sm:p-8">
            <div>
                <div class="text-xl font-semibold">{{ __('Own a khaja shop?') }}</div>
                <p class="mt-1 max-w-xl text-orange-100">
                    {{ __('Put your menu online free. Update it in 15 seconds a day and get found by hungry people nearby.') }}
                </p>
            </div>
            <a href="{{ route('register') }}" class="shrink-0 rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-orange-700 hover:bg-orange-50">
                {{ __('List your shop free') }}
            </a>
        </div>
    </section>
</div>