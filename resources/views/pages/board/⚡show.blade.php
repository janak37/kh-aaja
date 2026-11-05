<?php

use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use App\Services\ShopBoard;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public')] class extends Component {
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

    private function board(): ShopBoard
    {
        return new ShopBoard($this->shop);
    }
}; ?>

<div wire:poll.15s.visible class="mx-auto min-h-screen w-full max-w-xl pb-28">
    {{-- Header --}}
    <header class="bg-white px-4 pb-5 pt-6 shadow-sm dark:bg-zinc-800">
        <flux:heading size="xl" level="1">{{ $shop->name }}</flux:heading>

        <flux:text class="mt-1">
            {{ $shop->categoryLabel() }} · {{ $shop->area }}
            @if ($shop->opens_at && $shop->closes_at)
                · {{ substr($shop->opens_at, 0, 5) }}–{{ substr($shop->closes_at, 0, 5) }}
            @endif
        </flux:text>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            @if ($shop->isOpenNow() === true)
                <flux:badge color="green" size="sm">{{ __('Open now') }}</flux:badge>
            @elseif ($shop->isOpenNow() === false)
                <flux:badge color="zinc" size="sm">{{ __('Closed now') }}</flux:badge>
            @endif

            @if ($this->updatedAgo)
                <flux:text size="sm">{{ __('Board updated :ago', ['ago' => $this->updatedAgo]) }}</flux:text>
            @else
                <flux:badge color="amber" size="sm">{{ __('Not updated today') }}</flux:badge>
            @endif
        </div>

        @if ($shop->description)
            <flux:text class="mt-3">{{ $shop->description }}</flux:text>
        @endif
    </header>

    {{-- Today's specials --}}
    @if ($this->specials->isNotEmpty())
        <section class="px-4 pt-6">
            <flux:heading size="lg">⭐ {{ __("Today's specials") }}</flux:heading>

            <div class="mt-3 space-y-3">
                @foreach ($this->specials as $item)
                    @php
                        $row = $this->statuses->get($item->id);
                        $price = $row?->price ?? $item->default_price;
                    @endphp

                    <article wire:key="special-{{ $item->id }}" class="overflow-hidden rounded-2xl border border-amber-300 bg-white dark:border-amber-500/40 dark:bg-zinc-800">
                        @if ($item->photoUrl())
                            <img src="{{ $item->photoUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-40 w-full object-cover sm:h-56">
                        @endif

                        <div class="space-y-2 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-lg font-semibold">{{ $item->name }}</span>
                                    @if ($item->is_veg)
                                        <flux:badge color="green" size="sm">{{ __('Veg') }}</flux:badge>
                                    @endif
                                </div>
                                @if ($price)
                                    <span class="shrink-0 text-lg font-semibold">Rs {{ number_format($price) }}</span>
                                @endif
                            </div>

                            <x-status-pill :status="$row?->status" />

                            @if ($row?->special_note)
                                <flux:text>{{ $row->special_note }}</flux:text>
                            @endif

                            @if ($shop->whatsappUrl($item->name))
                                <a href="{{ $shop->whatsappUrl($item->name) }}" target="_blank" rel="noopener" class="inline-block text-sm font-medium text-green-700 underline dark:text-green-400">
                                    {{ __('Ask on WhatsApp') }}
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Full menu --}}
    <section class="px-4 pt-6">
        <flux:heading size="lg">{{ __('Menu') }}</flux:heading>

        <div class="mt-3 divide-y divide-zinc-200 overflow-hidden rounded-2xl bg-white dark:divide-zinc-700 dark:bg-zinc-800">
            @forelse ($this->items as $item)
                @php
                    $row = $this->statuses->get($item->id);
                    $price = $row?->price ?? $item->default_price;
                    $soldOut = $row?->status === \App\Enums\ItemStatus::SoldOut;
                @endphp

                <div wire:key="menu-{{ $item->id }}" @class(['flex items-center gap-3 p-3', 'opacity-50' => $soldOut])>
                    @if ($item->photoUrl())
                        <img src="{{ $item->photoUrl() }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-xl object-cover">
                    @else
                        <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-400 dark:bg-zinc-900">
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
                            @if ($shop->whatsappUrl($item->name) && ! $soldOut)
                                <a href="{{ $shop->whatsappUrl($item->name) }}" target="_blank" rel="noopener" class="text-xs font-medium text-green-700 underline dark:text-green-400">
                                    {{ __('Ask') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    @if ($price)
                        <span class="shrink-0 font-semibold">Rs {{ number_format($price) }}</span>
                    @endif
                </div>
            @empty
                <flux:text class="p-4">{{ __('This shop has not added its menu yet.') }}</flux:text>
            @endforelse
        </div>
    </section>

    {{-- Sticky contact bar --}}
    <nav class="fixed inset-x-0 bottom-0 border-t border-zinc-200 bg-white/95 p-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
        <div class="mx-auto flex max-w-xl gap-3">
            <a href="tel:{{ $shop->phone }}" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-zinc-900 py-3 font-medium text-white dark:bg-white dark:text-zinc-900">
                <flux:icon.phone class="size-5" />
                {{ __('Call') }}
            </a>

            @if ($shop->whatsappUrl())
                <a href="{{ $shop->whatsappUrl() }}" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-green-600 py-3 font-medium text-white">
                    <flux:icon.chat-bubble-left-right class="size-5" />
                    {{ __('WhatsApp') }}
                </a>
            @endif
        </div>
    </nav>
</div>