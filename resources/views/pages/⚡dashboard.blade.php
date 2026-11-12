<?php

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Services\ShopBoard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function isOwner(): bool
    {
        return Auth::user()->hasRole(UserRole::ShopOwner);
    }

    #[Computed]
    public function shop(): ?Shop
    {
        return Auth::user()->shop;
    }

    #[Computed]
    public function firstName(): string
    {
        return Str::before(Auth::user()->name, ' ');
    }

    /**
     * Everything the dashboard shows about today, in one place.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function summary(): array
    {
        $shop = $this->shop;

        if ($shop === null) {
            return [];
        }

        $board = new ShopBoard($shop);
        $items = $board->items();

        // Only rows for dishes still on the menu (archived dishes don't count).
        $statuses = $board->statuses()->whereIn('item_id', $items->modelKeys());

        return [
            'dishes' => $items->count(),
            'withPhoto' => $items->whereNotNull('photo_path')->count(),
            'set' => $statuses->count(),
            'available' => $statuses->where('status', ItemStatus::Available)->count(),
            'low' => $statuses->where('status', ItemStatus::Low)->count(),
            'soldOut' => $statuses->where('status', ItemStatus::SoldOut)->count(),
            'specials' => $statuses->where('is_special', true)->count(),
            'lastUpdated' => $board->lastUpdatedAt()?->timezone($shop->timezone)->format('g:i a'),
            'streak' => $board->streak(),
            'updatedDays' => $board->updatedDaysInWindow(),
            'reliable' => $board->isReliable(),
        ];
    }

    /**
     * First-week checklist; hidden once every step is done.
     *
     * @return list<array{label: string, done: bool, route: string}>
     */
    #[Computed]
    public function steps(): array
    {
        $shop = $this->shop;

        if ($shop === null) {
            return [];
        }

        $s = $this->summary;

        return [
            ['label' => __('Add at least 3 dishes to your menu'), 'done' => $s['dishes'] >= 3, 'route' => 'shop.menu'],
            ['label' => __('Add a photo to a dish'), 'done' => $s['withPhoto'] >= 1, 'route' => 'shop.menu'],
            ['label' => __('Set today\'s board'), 'done' => $s['set'] >= 1, 'route' => 'shop.today'],
            ['label' => __('Add your WhatsApp number'), 'done' => $shop->whatsapp_number !== null, 'route' => 'shop.edit'],
        ];
    }

    #[Computed]
    public function stepsLeft(): int
    {
        return count(array_filter($this->steps, fn (array $step) => ! $step['done']));
    }
}; ?>

<section class="w-full max-w-4xl space-y-6">
    @if ($this->isOwner && $this->shop === null)
        {{-- New owner: one clear next step --}}
        <div class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="xl" level="1">{{ __('Welcome to Kh-aaja, :name', ['name' => $this->firstName]) }}</flux:heading>
            <flux:text>{{ __('Set up your shop to get your own board link and QR code. It takes about a minute.') }}</flux:text>
            <flux:button variant="primary" icon="building-storefront" :href="route('shop.edit')" wire:navigate>
                {{ __('Set up your shop') }}
            </flux:button>
        </div>
    @elseif ($this->shop)
        @php($s = $this->summary)

        {{-- Greeting --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Namaste, :name', ['name' => $this->firstName]) }}</flux:heading>
                <flux:subheading>{{ $this->shop->name }} · {{ $this->shop->area }}</flux:subheading>
            </div>
            <flux:button size="sm" icon="eye" :href="route('board.show', $this->shop)" target="_blank">
                {{ __('View my board') }}
            </flux:button>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            {{-- Today's board --}}
            <div class="space-y-4 rounded-xl border border-zinc-200 p-5 md:col-span-2 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="lg">{{ __('Today\'s board') }}</flux:heading>
                    @if ($s['lastUpdated'])
                        <flux:text size="sm">{{ __('Last updated :time', ['time' => $s['lastUpdated']]) }}</flux:text>
                    @else
                        <flux:badge color="amber" size="sm">{{ __('Not updated yet today') }}</flux:badge>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-lg bg-green-100 p-3 dark:bg-green-500/15">
                        <div class="text-2xl font-bold text-green-800 dark:text-green-300">{{ $s['available'] }}</div>
                        <div class="text-xs text-green-800 dark:text-green-300">{{ __('Available') }}</div>
                    </div>
                    <div class="rounded-lg bg-amber-100 p-3 dark:bg-amber-500/15">
                        <div class="text-2xl font-bold text-amber-800 dark:text-amber-300">{{ $s['low'] }}</div>
                        <div class="text-xs text-amber-800 dark:text-amber-300">{{ __('Low') }}</div>
                    </div>
                    <div class="rounded-lg bg-red-100 p-3 dark:bg-red-500/15">
                        <div class="text-2xl font-bold text-red-800 dark:text-red-300">{{ $s['soldOut'] }}</div>
                        <div class="text-xs text-red-800 dark:text-red-300">{{ __('Sold out') }}</div>
                    </div>
                </div>

                <flux:text>
                    {{ __(':set of :total dishes set today', ['set' => $s['set'], 'total' => $s['dishes']]) }}
                    · {{ __(':count of :max specials', ['count' => $s['specials'], 'max' => ShopBoard::MAX_SPECIALS]) }}
                </flux:text>

                <flux:button variant="primary" icon="clipboard-document-check" :href="route('shop.today')" wire:navigate>
                    {{ $s['set'] > 0 ? __('Update today\'s board') : __('Set today\'s board') }}
                </flux:button>
            </div>

            {{-- Streak and badge --}}
            <div class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Your streak') }}</flux:heading>

                <div class="flex items-baseline gap-2">
                    <flux:icon.fire @class(['size-8', 'text-orange-500' => $s['streak'] > 0, 'text-zinc-400' => $s['streak'] === 0]) />
                    <span class="text-4xl font-bold">{{ $s['streak'] }}</span>
                </div>
                <flux:text>{{ __(':count-day streak', ['count' => $s['streak']]) }}</flux:text>

                @if ($s['reliable'])
                    <flux:badge color="green" icon="check-badge">{{ __('Reliable board') }}</flux:badge>
                @else
                    @php($percent = min(100, (int) round($s['updatedDays'] / ShopBoard::RELIABLE_WINDOW_DAYS * 100)))
                    @php($target = (int) round(ShopBoard::RELIABLE_MIN_DAYS / ShopBoard::RELIABLE_WINDOW_DAYS * 100))

                    <div class="relative h-2 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                        <div class="h-2 rounded-full bg-green-500" style="width: {{ $percent }}%"></div>
                        <div class="absolute -top-1 h-4 w-0.5 bg-zinc-500" style="left: {{ $target }}%" title="{{ __('Reliable board') }}"></div>
                    </div>
                    <flux:text size="sm">
                        {{ __(':days of :min days for the Reliable board badge', [
                            'days' => $s['updatedDays'],
                            'min' => ShopBoard::RELIABLE_MIN_DAYS,
                        ]) }}
                    </flux:text>
                @endif
            </div>
        </div>

        {{-- Getting started (disappears when all done) --}}
        @if ($this->stepsLeft > 0)
            <div class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Getting started') }}</flux:heading>
                <ul class="space-y-2">
                    @foreach ($this->steps as $step)
                        <li class="flex items-center gap-3">
                            @if ($step['done'])
                                <flux:icon.check-circle class="size-5 text-green-500" />
                                <span class="text-zinc-500 line-through dark:text-zinc-400">{{ $step['label'] }}</span>
                            @else
                                <flux:icon.minus-circle class="size-5 text-zinc-400" />
                                <flux:link :href="route($step['route'])" wire:navigate>{{ $step['label'] }}</flux:link>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Quick links --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <a href="{{ route('shop.menu') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                <flux:icon.book-open class="size-6 text-zinc-500" />
                <div>
                    <div class="font-medium">{{ __('Menu') }}</div>
                    <flux:text size="sm">{{ trans_choice(':count dish|:count dishes', $s['dishes']) }}</flux:text>
                </div>
            </a>
            <a href="{{ route('shop.qr') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                <flux:icon.qr-code class="size-6 text-zinc-500" />
                <div>
                    <div class="font-medium">{{ __('QR code') }}</div>
                    <flux:text size="sm">{{ __('Print your table card') }}</flux:text>
                </div>
            </a>
            <a href="{{ route('shop.edit') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                <flux:icon.building-storefront class="size-6 text-zinc-500" />
                <div>
                    <div class="font-medium">{{ __('My shop') }}</div>
                    <flux:text size="sm">{{ __('Hours, phone, WhatsApp') }}</flux:text>
                </div>
            </a>
        </div>
    @else
        {{-- Admin and anyone else --}}
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Namaste, :name', ['name' => $this->firstName]) }}</flux:heading>
            <flux:text>{{ __('Welcome to Kh-aaja.') }}</flux:text>
        </div>
    @endif
</section>