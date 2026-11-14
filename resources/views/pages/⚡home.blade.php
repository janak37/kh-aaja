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

<div wire:poll.30s.visible class="mx-auto min-h-screen w-full max-w-3xl pb-16">
    {{-- Top bar --}}
    <header class="flex items-center justify-between gap-3 px-4 pt-5">
        <a href="{{ route('home') }}" class="text-lg font-bold">
            Kh-aaja <span class="font-normal text-zinc-500">· खाजा आज</span>
        </a>

        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="heart" :href="route('favourites')" :aria-label="__('Favourites')" :tooltip="__('Favourites')" />
            <x-language-switch />
            @auth
                <flux:button size="sm" :href="route('dashboard')">{{ __('Dashboard') }}</flux:button>
            @else
                <flux:button size="sm" variant="ghost" :href="route('login')">{{ __('Log in') }}</flux:button>
            @endauth
        </div>
    </header>

    {{-- Search and filters --}}
    <section class="px-4 pt-8">
        <flux:heading size="xl" level="1">{{ __('What can you eat nearby right now?') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Live boards from local khaja shops, updated today.') }}</flux:text>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="q" icon="magnifying-glass" :placeholder="__('Search momo, thakali, shop…')" />
            </div>
            <div class="sm:w-48">
                <flux:select wire:model.live="area">
                    <flux:select.option value="">{{ __('All areas') }}</flux:select.option>
                    @foreach (config('khaaja.areas') as $areaName)
                        <flux:select.option :value="$areaName">{{ $areaName }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
            <button type="button" wire:click="$set('category', '')" @class([
                'shrink-0 rounded-full border px-3 py-1 text-sm transition',
                'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $category === '',
                'border-zinc-300 text-zinc-700 dark:border-zinc-600 dark:text-zinc-300' => $category !== '',
            ])>{{ __('All') }}</button>

            @foreach (config('khaaja.categories') as $value => $label)
                <button type="button" wire:click="toggleCategory('{{ $value }}')" @class([
                    'shrink-0 rounded-full border px-3 py-1 text-sm transition',
                    'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $category === $value,
                    'border-zinc-300 text-zinc-700 dark:border-zinc-600 dark:text-zinc-300' => $category !== $value,
                ])>{{ __($label) }}</button>
            @endforeach
        </div>
    </section>

    {{-- Today's specials, all shops --}}
    @if ($this->specials->isNotEmpty())
        <section class="pt-8">
            <flux:heading size="lg" class="px-4">⭐ {{ __("Today's specials") }}</flux:heading>

            <div class="mt-3 flex snap-x gap-3 overflow-x-auto px-4 pb-2">
                @foreach ($this->specials as $special)
                    @php
                        $item = $special->item;
                        $shop = $item->shop;
                        $price = $special->price ?? $item->default_price;
                    @endphp

                    <div wire:key="special-{{ $special->id }}" class="relative w-56 shrink-0 snap-start overflow-hidden rounded-2xl border border-amber-300 bg-white dark:border-amber-500/40 dark:bg-zinc-800">
                        <a href="{{ route('board.show', $shop) }}" class="block">
                            @if ($item->photoUrl())
                                <img src="{{ $item->photoUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-32 w-full object-cover">
                            @else
                                <div class="flex h-32 items-center justify-center bg-zinc-100 text-zinc-400 dark:bg-zinc-900">
                                    <flux:icon.photo class="size-8" />
                                </div>
                            @endif

                            <div class="space-y-1 p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-semibold leading-tight">{{ $item->name }}</span>
                                    @if ($price)
                                        <span class="shrink-0 text-sm font-semibold">Rs {{ number_format($price) }}</span>
                                    @endif
                                </div>
                                <div class="truncate text-xs text-zinc-500">{{ $shop->name }} · {{ $shop->area }}</div>
                                <x-status-pill :status="$special->status" />
                            </div>
                        </a>

                        <x-fav-heart :item="$item" class="absolute right-2 top-2 bg-white/90 shadow-sm dark:bg-zinc-900/80" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Shops --}}
    <section class="px-4 pt-8">
        <flux:heading size="lg">{{ __('Shops') }}</flux:heading>

        <div class="mt-3 space-y-3">
            @forelse ($this->shops as $shop)
                @php
                    $ready = $shop->items->filter(fn (Item $item) => in_array(
                        $item->dailyStatuses->first()?->status,
                        [ItemStatus::Available, ItemStatus::Low],
                        true,
                    ));
                @endphp

                <div wire:key="shop-{{ $shop->id }}" @class([
                    'flex gap-3 rounded-2xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-800',
                    'opacity-60' => ! $shop->updated_today,
                ])>
                    <a href="{{ route('board.show', $shop) }}" class="min-w-0 flex-1">
                        <div class="truncate font-semibold">{{ $shop->name }}</div>
                        <div class="text-sm text-zinc-500">{{ $shop->categoryLabel() }} · {{ $shop->area }}</div>

                        @if ($ready->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($ready->take(4) as $item)
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs dark:bg-zinc-700">{{ $item->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </a>

                    <div class="flex shrink-0 flex-col items-end justify-between gap-2">
                        @if ($shop->updated_today)
                            <flux:badge color="green" size="sm">
                                {{ trans_choice(':count dish ready|:count dishes ready', $ready->count()) }}
                            </flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Not updated today') }}</flux:badge>
                        @endif

                        <div class="flex items-center text-sm text-zinc-500">
                            @if ($shop->favourites_count > 0)
                                <span>{{ $shop->favourites_count }}</span>
                            @endif
                            <x-fav-heart :shop="$shop" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600">
                    <flux:text>{{ __('No shops match your search.') }}</flux:text>
                    <flux:button size="sm" class="mt-3" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                </div>
            @endforelse
        </div>
    </section>

    {{-- For shop owners --}}
    <section class="px-4 pt-10">
        <div class="rounded-2xl bg-zinc-900 p-6 text-white dark:bg-zinc-800">
            <div class="text-lg font-semibold">{{ __('Own a khaja shop?') }}</div>
            <p class="mt-1 text-sm text-zinc-300">
                {{ __('Put your menu online free. Update it in 15 seconds a day and get found by hungry people nearby.') }}
            </p>
            <a href="{{ route('register') }}" class="mt-4 inline-block rounded-lg bg-white px-4 py-2 text-sm font-semibold text-zinc-900">
                {{ __('List your shop free') }}
            </a>
        </div>
    </section>
</div>