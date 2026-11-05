<?php

use App\Enums\ItemStatus;
use App\Models\DailyStatus;
use App\Models\Item;
use App\Models\Shop;
use App\Services\ShopBoard;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Today')] class extends Component {
    /**
     * An owner must set up their shop first.
     */
    public function mount(): void
    {
        if ($this->shop === null) {
            $this->redirectRoute('shop.edit', navigate: true);
        }
    }

    #[Computed]
    public function shop(): ?Shop
    {
        return Auth::user()->shop;
    }

    /**
     * @return Collection<int, Item>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->shop === null ? new Collection : $this->board()->items();
    }

    /**
     * @return Collection<int, DailyStatus>
     */
    #[Computed]
    public function statuses(): Collection
    {
        return $this->shop === null ? new Collection : $this->board()->statuses();
    }

    #[Computed]
    public function lastUpdated(): ?string
    {
        if ($this->shop === null) {
            return null;
        }

        return $this->board()->lastUpdatedAt()?->timezone($this->shop->timezone)->format('g:i a');
    }

    #[Computed]
    public function streak(): int
    {
        return $this->shop === null ? 0 : $this->board()->streak();
    }

    #[Computed]
    public function updatedDays(): int
    {
        return $this->shop === null ? 0 : $this->board()->updatedDaysInWindow();
    }

    #[Computed]
    public function isReliable(): bool
    {
        return $this->shop !== null && $this->board()->isReliable();
    }

    public function setStatus(int $itemId, string $status): void
    {
        $case = ItemStatus::tryFrom($status);

        abort_if($case === null, 422);

        $this->board()->setStatus($this->ownItem($itemId), $case);
        $this->refreshBoard();
    }

    public function setPrice(int $itemId, string $value): void
    {
        $value = trim($value);

        if ($value !== '' && (! ctype_digit($value) || (int) $value < 1 || (int) $value > 100000)) {
            Flux::toast(variant: 'danger', text: __('Price must be a whole number of rupees.'));

            return;
        }

        $this->board()->setPrice($this->ownItem($itemId), $value === '' ? null : (int) $value);
        $this->refreshBoard();
    }

    public function toggleSpecial(int $itemId): void
    {
        try {
            $this->board()->toggleSpecial($this->ownItem($itemId));
        } catch (DomainException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        $this->refreshBoard();
    }

    public function copyYesterday(): void
    {
        $copied = $this->board()->copyYesterday();

        Flux::toast(
            variant: $copied > 0 ? 'success' : 'warning',
            text: $copied > 0
                ? trans_choice(':count dish copied from yesterday.|:count dishes copied from yesterday.', $copied)
                : __('Nothing to copy: the board was not updated yesterday.'),
        );

        $this->refreshBoard();
    }

    public function resetAll(): void
    {
        $this->board()->resetAllToAvailable();

        Flux::toast(variant: 'success', text: __('All dishes marked available.'));
        $this->refreshBoard();
    }

    private function board(): ShopBoard
    {
        $shop = $this->shop;

        abort_if($shop === null, 403);

        return new ShopBoard($shop);
    }

    private function ownItem(int $id): Item
    {
        $shop = $this->shop;

        abort_if($shop === null, 403);

        return $shop->items()->active()->findOrFail($id);
    }

    /**
     * Forget cached values so the page shows the change straight away
     * (the first tap of the day turns "4-day streak" into "5-day streak").
     */
    private function refreshBoard(): void
    {
        unset($this->statuses, $this->lastUpdated, $this->streak, $this->updatedDays, $this->isReliable);
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Today') }}</flux:heading>
            <flux:subheading>
                {{ now($this->shop?->timezone ?? 'Asia/Kathmandu')->format('l, j F') }} ·
                @if ($this->lastUpdated)
                    {{ __('Last updated :time', ['time' => $this->lastUpdated]) }}
                @else
                    {{ __('Not updated yet today') }}
                @endif
            </flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($this->shop)
                <flux:button size="sm" icon="eye" :href="route('board.show', $this->shop)" target="_blank">{{ __('View my board') }}</flux:button>
            @endif
            <flux:button size="sm" icon="document-duplicate" wire:click="copyYesterday">{{ __('Copy yesterday') }}</flux:button>
            <flux:button size="sm" icon="check-circle" wire:click="resetAll">{{ __('All available') }}</flux:button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <flux:badge :color="$this->streak > 0 ? 'orange' : 'zinc'" icon="fire">
            {{ __(':count-day streak', ['count' => $this->streak]) }}
        </flux:badge>

        @if ($this->isReliable)
            <flux:badge color="green" icon="check-badge">{{ __('Reliable board') }}</flux:badge>
        @else
            <flux:text size="sm">
                {{ __('Updated :days of the last :window days. Reach :min for the Reliable board badge.', [
                    'days' => $this->updatedDays,
                    'window' => ShopBoard::RELIABLE_WINDOW_DAYS,
                    'min' => ShopBoard::RELIABLE_MIN_DAYS,
                ]) }}
            </flux:text>
        @endif
    </div>

    <flux:text>
        {{ __('Specials today: :count of :max', [
            'count' => $this->statuses->where('is_special', true)->count(),
            'max' => ShopBoard::MAX_SPECIALS,
        ]) }}
    </flux:text>

    <div class="space-y-3">
        @forelse ($this->items as $item)
            @php
                $row = $this->statuses->get($item->id);
                $status = $row?->status;
                $isSpecial = (bool) $row?->is_special;
            @endphp

            <div wire:key="today-{{ $item->id }}" @class([
                'space-y-3 rounded-xl border p-3',
                'border-amber-400 bg-amber-50 dark:bg-amber-950/30' => $isSpecial,
                'border-zinc-200 dark:border-zinc-700' => ! $isSpecial,
            ])>
                <div class="flex items-center gap-3">
                    @if ($item->photoUrl())
                        <img src="{{ $item->photoUrl() }}" alt="" loading="lazy" class="size-12 shrink-0 rounded-lg object-cover">
                    @else
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-400 dark:bg-zinc-900">
                            <flux:icon.photo class="size-5" />
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $item->name }}</div>
                        <flux:text size="sm">{{ $status ? $status->label() : __('Not set today') }}</flux:text>
                    </div>

                    <div class="relative w-28 shrink-0">
                        <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-sm text-zinc-500 dark:text-zinc-400">
                            Rs
                        </span>
                        <input
                            type="number"
                            inputmode="numeric"
                            min="1"
                            value="{{ $row?->price }}"
                            placeholder="{{ $item->default_price ?? '' }}"
                            wire:change="setPrice({{ $item->id }}, $event.target.value)"
                            aria-label="{{ __('Today\'s price') }}"
                            class="w-full rounded-lg border border-zinc-200 bg-white py-1.5 ps-9 pe-2 text-sm text-zinc-800 placeholder-zinc-400 focus:border-zinc-400 focus:outline-none dark:border-zinc-600 dark:bg-white/10 dark:text-white dark:placeholder-zinc-500"
                        >
                    </div>

                    <flux:button
                        size="sm"
                        :variant="$isSpecial ? 'primary' : 'ghost'"
                        icon="star"
                        wire:click="toggleSpecial({{ $item->id }})"
                        :aria-label="__('Today\'s special')"
                    />
                </div>

                <div class="grid grid-cols-3 gap-2">
                    @foreach (ItemStatus::cases() as $case)
                        <button
                            type="button"
                            wire:click="setStatus({{ $item->id }}, '{{ $case->value }}')"
                            @class([
                                'rounded-lg border px-2 py-2.5 text-sm font-medium transition',
                                'border-green-600 bg-green-600 text-white' => $status === $case && $case === ItemStatus::Available,
                                'border-amber-500 bg-amber-500 text-white' => $status === $case && $case === ItemStatus::Low,
                                'border-red-600 bg-red-600 text-white' => $status === $case && $case === ItemStatus::SoldOut,
                                'border-zinc-300 text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-700' => $status !== $case,
                            ])
                        >
                            {{ $case->label() }}
                        </button>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:text>
                {{ __('Your menu is empty.') }}
                <flux:link :href="route('shop.menu')" wire:navigate>{{ __('Add your dishes first') }}</flux:link>
            </flux:text>
        @endforelse
    </div>
</section>