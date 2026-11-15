<?php

use App\Livewire\Concerns\CountsFavourites;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use App\Services\ShopBoard;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public')] class extends Component {
    use CountsFavourites;

    public Shop $shop;

    public function mount(Shop $shop): void
    {
        abort_if($shop->isSuspended(), 404);

        $this->shop = $shop;
    }

    /**
     * @return Collection<int, Item>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->board()->items();
    }

    /**
     * Today's rows only (shop's local date), keyed by item_id.
     *
     * @return Collection<int, DailyStatus>
     */
    #[Computed]
    public function statuses(): Collection
    {
        return $this->board()->statuses();
    }

    /**
     * @return Collection<int, Item>
     */
    #[Computed]
    public function specials(): Collection
    {
        return $this->items
            ->filter(fn (Item $item) => (bool) ($this->statuses->get($item->id)->is_special ?? false))
            ->values();
    }

    #[Computed]
    public function updatedAgo(): ?string
    {
        return $this->board()->lastUpdatedAt()?->diffForHumans();
    }

    #[Computed]
    public function streak(): int
    {
        return $this->board()->streak();
    }

    #[Computed]
    public function isReliable(): bool
    {
        return $this->board()->isReliable();
    }

    private function board(): ShopBoard
    {
        return new ShopBoard($this->shop);
    }
}; ?>

<div wire:poll.15s.visible class="mx-auto w-full max-w-4xl pb-28 sm:px-6">
    {{-- Back to all shops --}}
    <div class="px-4 pt-4 sm:px-0">
        <a href="{{ route('home') }}" class="text-sm font-medium text-stone-500 hover:text-orange-700">← {{ __('All shops') }}</a>
    </div>

    {{-- Shop header --}}
    <header class="mt-3 bg-white px-4 py-6 shadow-sm ring-1 ring-stone-200 sm:rounded-2xl sm:px-8">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $shop->name }}</h1>
                <p class="mt-1 text-stone-500">
                    {{ $shop->categoryLabel() }} · {{ $shop->area }}
                    @if ($shop->opens_at && $shop->closes_at)
                        · {{ substr($shop->opens_at, 0, 5) }}–{{ substr($shop->closes_at, 0, 5) }}
                    @endif
                </p>
            </div>

            <div class="flex shrink-0 items-center rounded-full bg-orange-50 py-0.5 pl-3 pr-0.5 text-sm font-medium text-orange-800">
                <span>{{ $shop->favourites_count }}</span>
                <x-fav-heart :shop="$shop" />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if ($this->isReliable)
                <flux:badge color="green" size="sm" icon="check-badge">{{ __('Reliable board') }}</flux:badge>
            @endif

            @if ($shop->isOpenNow() === true)
                <flux:badge color="green" size="sm">{{ __('Open now') }}</flux:badge>
            @elseif ($shop->isOpenNow() === false)
                <flux:badge color="zinc" size="sm">{{ __('Closed now') }}</flux:badge>
            @endif

            @if ($this->updatedAgo)
                <span class="text-sm text-stone-500">
                    {{ __('Board updated :ago', ['ago' => $this->updatedAgo]) }}
                    @if ($this->streak >= 3)
                        · 🔥 {{ __(':count days in a row', ['count' => $this->streak]) }}
                    @endif
                </span>
            @else
                <flux:badge color="amber" size="sm">{{ __('Not updated today') }}</flux:badge>
            @endif
        </div>

        @if ($shop->description)
            <p class="mt-4 max-w-2xl text-stone-600">{{ $shop->description }}</p>
        @endif
    </header>

    {{-- Today's specials: 1 column on phones, 2 on bigger screens --}}
    @if ($this->specials->isNotEmpty())
        <section class="px-4 pt-8 sm:px-0">
            <h2 class="text-xl font-semibold">⭐ {{ __("Today's specials") }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($this->specials as $item)
                    @php
                        $row = $this->statuses->get($item->id);
                        $price = $row?->price ?? $item->default_price;
                    @endphp

                    <article wire:key="special-{{ $item->id }}" class="flex flex-col overflow-hidden rounded-2xl border border-orange-200 bg-white shadow-sm">
                        @if ($item->photoUrl())
                            <img src="{{ $item->photoUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-44 w-full object-cover">
                        @endif

                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-lg font-semibold">{{ $item->name }}</span>
                                    @if ($item->is_veg)
                                        <flux:badge color="green" size="sm">{{ __('Veg') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    @if ($price)
                                        <span class="text-lg font-semibold text-orange-700">Rs {{ number_format($price) }}</span>
                                    @endif
                                    <x-fav-heart :item="$item" />
                                </div>
                            </div>

                            <x-status-pill :status="$row?->status" />

                            @if ($row?->special_note)
                                <p class="text-sm text-stone-600">{{ $row->special_note }}</p>
                            @endif

                            <div class="mt-auto flex flex-wrap items-center gap-3 pt-2">
                                @if (in_array($row?->status, [\App\Enums\ItemStatus::Available, \App\Enums\ItemStatus::Low], true))
                                    <a href="{{ route('board.reserve', [$shop, $item]) }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">
                                        {{ __('Reserve') }}
                                    </a>
                                @endif
                                @if ($shop->whatsappUrl($item->name))
                                    <a href="{{ $shop->whatsappUrl($item->name) }}" target="_blank" rel="noopener" class="text-sm font-medium text-green-700 underline">
                                        {{ __('Ask on WhatsApp') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Full menu --}}
    <section class="px-4 pt-8 sm:px-0">
        <h2 class="text-xl font-semibold">{{ __('Menu') }}</h2>

        <div class="mt-4 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
            @forelse ($this->items as $item)
                @php
                    $row = $this->statuses->get($item->id);
                    $price = $row?->price ?? $item->default_price;
                    $soldOut = $row?->status === \App\Enums\ItemStatus::SoldOut;
                @endphp

                <div wire:key="menu-{{ $item->id }}" class="flex items-center gap-3 p-3 sm:px-5 sm:py-4">
                    <div @class(['flex min-w-0 flex-1 items-center gap-3 sm:gap-4', 'opacity-50' => $soldOut])>
                        @if ($item->photoUrl())
                            <img src="{{ $item->photoUrl() }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-xl object-cover sm:size-16">
                        @else
                            <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-300 sm:size-16">
                                <flux:icon.photo class="size-5" />
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium">{{ $item->name }}</span>
                                @if ($item->is_veg)
                                    <flux:badge color="green" size="sm">{{ __('Veg') }}</flux:badge>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <x-status-pill :status="$row?->status" />
                                @if (in_array($row?->status, [\App\Enums\ItemStatus::Available, \App\Enums\ItemStatus::Low], true))
                                    <a href="{{ route('board.reserve', [$shop, $item]) }}" class="text-xs font-semibold text-orange-700 underline underline-offset-2">
                                        {{ __('Reserve') }}
                                    </a>
                                @endif
                                @if ($shop->whatsappUrl($item->name) && ! $soldOut)
                                    <a href="{{ $shop->whatsappUrl($item->name) }}" target="_blank" rel="noopener" class="text-xs font-medium text-green-700 underline">
                                        {{ __('Ask') }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if ($price)
                            <span class="shrink-0 font-semibold">Rs {{ number_format($price) }}</span>
                        @endif
                    </div>

                    <x-fav-heart :item="$item" />
                </div>
            @empty
                <p class="p-6 text-center text-stone-500">{{ __('This shop has not added its menu yet.') }}</p>
            @endforelse
        </div>
    </section>

    {{-- Sticky contact bar --}}
    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-stone-200 bg-white/95 p-3 backdrop-blur">
        <div class="mx-auto flex max-w-4xl gap-3 sm:px-6">
            <a href="tel:{{ $shop->phone }}" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-orange-600 py-3 font-semibold text-white hover:bg-orange-700">
                <flux:icon.phone class="size-5" />
                {{ __('Call') }}
            </a>

            @if ($shop->whatsappUrl())
                <a href="{{ $shop->whatsappUrl() }}" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-green-600 py-3 font-semibold text-white hover:bg-green-700">
                    <flux:icon.chat-bubble-left-right class="size-5" />
                    {{ __('WhatsApp') }}
                </a>
            @endif
        </div>
    </nav>
</div>