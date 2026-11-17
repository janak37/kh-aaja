<?php

use App\Enums\ItemStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\BoardUpdate;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\Shop;
use App\Services\KhaajaScore;
use App\Services\ShopBoard;
use Illuminate\Database\Eloquent\Collection;
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

        $todayReservations = $shop->reservations()
            ->where('board_date', $board->date())
            ->get(['id', 'status']);

        return [
            'dishes' => $items->count(),
            'withPhoto' => $items->whereNotNull('photo_path')->count(),
            'set' => $statuses->count(),
            'available' => $statuses->where('status', ItemStatus::Available)->count(),
            'low' => $statuses->where('status', ItemStatus::Low)->count(),
            'soldOut' => $statuses->where('status', ItemStatus::SoldOut)->count(),
            'specials' => $statuses->where('is_special', true)->count(),
            'specialItems' => $items->whereIn('id', $statuses->where('is_special', true)->pluck('item_id')->all())->values(),
            'soldOutItems' => $items->whereIn('id', $statuses->where('status', ItemStatus::SoldOut)->pluck('item_id')->all())->values(),
            'lastUpdated' => $board->lastUpdatedAt()?->timezone($shop->timezone)->format('g:i a'),
            'streak' => $board->streak(),
            'updatedDays' => $board->updatedDaysInWindow(),
            'reliable' => $board->isReliable(),
            'reservationsToday' => $todayReservations->count(),
            'waiting' => $todayReservations->where('status', ReservationStatus::Pending)->count(),
            'hearts' => $shop->favourites_count + (int) $shop->items()->where('is_archived', false)->sum('favourites_count'),
            'score' => app(KhaajaScore::class)->summary($shop),
        ];
    }

    /**
     * The last 5 reservations for today, newest first.
     *
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function latestReservations(): Collection
    {
        $shop = $this->shop;

        if ($shop === null) {
            return new Collection;
        }

        return $shop->reservations()
            ->with('item')
            ->where('board_date', (new ShopBoard($shop))->date())
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * The 3 dishes customers saved most.
     *
     * @return Collection<int, Item>
     */
    #[Computed]
    public function lovedDishes(): Collection
    {
        $shop = $this->shop;

        if ($shop === null) {
            return new Collection;
        }

        return $shop->items()
            ->where('is_archived', false)
            ->where('favourites_count', '>', 0)
            ->reorder()
            ->orderByDesc('favourites_count')
            ->limit(3)
            ->get();
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

    /**
     * Today's numbers across Kh-aaja, for the admin.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function platform(): array
    {
        $today = now('Asia/Kathmandu')->toDateString();

        return [
            'shops' => Shop::query()->whereNull('suspended_at')->count(),
            'updated' => BoardUpdate::query()->where('board_date', $today)->count(),
            'reservations' => Reservation::query()->where('board_date', $today)->count(),
            'pro' => Shop::query()->where('is_pro', true)->count(),
        ];
    }
}; ?>

<section wire:poll.20s.visible class="w-full max-w-6xl space-y-6">
    @if ($this->isOwner && $this->shop === null)
        {{-- New owner: one clear next step --}}
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 to-orange-700 p-8 text-white shadow-sm sm:p-10">
            <h1 class="text-2xl font-bold sm:text-3xl">{{ __('Welcome to Kh-aaja, :name', ['name' => $this->firstName]) }}</h1>
            <p class="mt-2 max-w-xl text-orange-100">{{ __('Set up your shop to get your own board link and QR code. It takes about a minute.') }}</p>
            <a href="{{ route('shop.edit') }}" wire:navigate class="mt-6 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 font-semibold text-orange-700 shadow-sm hover:bg-orange-50">
                <flux:icon.building-storefront class="size-5" />
                {{ __('Set up your shop') }}
            </a>
        </div>
    @elseif ($this->shop)
        @php
            $s = $this->summary;
            $shop = $this->shop;
            $open = $shop->isOpenNow();
            $setPercent = $s['dishes'] > 0 ? (int) round($s['set'] / $s['dishes'] * 100) : 0;
            $card = 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200 sm:p-6';
        @endphp

        @if ($shop->isSuspended())
            <div class="flex gap-3 rounded-2xl border border-red-300 bg-red-50 p-4 text-red-800">
                <flux:icon.exclamation-triangle class="size-5 shrink-0" />
                <div>
                    <div class="font-medium">{{ __('Your shop is suspended') }}</div>
                    <div class="text-sm">{{ __('Customers cannot see your board right now. Please contact the Kh-aaja team.') }}</div>
                </div>
            </div>
        @endif

        {{-- Header --}}
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 to-orange-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl font-bold ring-1 ring-white/30">
                        {{ Str::upper(Str::substr($shop->name, 0, 1)) }}
                    </span>
                    <div>
                        <p class="text-sm text-orange-100">{{ __('Namaste, :name', ['name' => $this->firstName]) }}</p>
                        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $shop->name }}</h1>
                        <p class="text-sm text-orange-100">{{ $shop->categoryLabel() }} · {{ $shop->area }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('board.show', $shop) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold ring-1 ring-white/30 hover:bg-white/25">
                        <flux:icon.eye class="size-4" />
                        {{ __('View my board') }}
                    </a>
                    <a href="{{ route('shop.today') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-orange-700 shadow-sm hover:bg-orange-50">
                        <flux:icon.clipboard-document-check class="size-4" />
                        {{ $s['set'] > 0 ? __('Update today\'s board') : __('Set today\'s board') }}
                    </a>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium">
                @if ($open !== null)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 ring-1 ring-white/25">
                        <span @class(['size-2 rounded-full', 'bg-green-300' => $open, 'bg-stone-300' => ! $open])></span>
                        {{ $open ? __('Open now') : __('Closed now') }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 ring-1 ring-white/25">
                    <span @class(['size-2 rounded-full', 'bg-green-300' => $s['lastUpdated'], 'bg-amber-300' => ! $s['lastUpdated']])></span>
                    {{ $s['lastUpdated'] ? __('Last updated :time', ['time' => $s['lastUpdated']]) : __('Not updated yet today') }}
                </span>
                @if ($s['reliable'])
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-green-700">
                        <flux:icon.check-badge class="size-4" />
                        {{ __('Reliable board') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Waiting reservations --}}
        @if ($s['waiting'] > 0)
            <a href="{{ route('shop.reservations') }}" wire:navigate class="flex items-center justify-between gap-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-sm hover:bg-amber-100">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-amber-200/70">
                        <flux:icon.inbox class="size-5" />
                    </span>
                    <div>
                        <div class="font-semibold">{{ __('Reservations waiting') }}</div>
                        <div class="text-sm">{{ trans_choice(':count waiting|:count waiting', $s['waiting']) }}</div>
                    </div>
                </div>
                <span class="text-sm font-semibold underline">{{ __('Open reservations') }}</span>
            </a>
        @endif

        {{-- Key numbers --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="{{ $card }}">
                <div class="flex items-center gap-2 text-sm text-stone-500">
                    <flux:icon.chart-bar class="size-4 text-orange-600" />
                    {{ __('Kh-aaja score') }}
                </div>
                <div class="mt-2 text-3xl font-bold">{{ $s['score']['total'] }}<span class="text-base font-normal text-stone-400">/100</span></div>
                <div class="mt-3 h-1.5 rounded-full bg-stone-100">
                    <div class="h-1.5 rounded-full bg-orange-500" style="width: {{ $s['score']['total'] }}%"></div>
                </div>
            </div>

            <div class="{{ $card }}">
                <div class="flex items-center gap-2 text-sm text-stone-500">
                    <flux:icon.fire class="size-4 text-orange-600" />
                    {{ __('Your streak') }}
                </div>
                <div class="mt-2 text-3xl font-bold">{{ $s['streak'] }}</div>
                <div class="mt-1 text-sm text-stone-500">{{ __(':count-day streak', ['count' => $s['streak']]) }}</div>
            </div>

            <div class="{{ $card }}">
                <div class="flex items-center gap-2 text-sm text-stone-500">
                    <flux:icon.inbox class="size-4 text-orange-600" />
                    {{ __('Reservations today') }}
                </div>
                <div class="mt-2 text-3xl font-bold">{{ $s['reservationsToday'] }}</div>
                <div @class(['mt-1 text-sm', 'font-medium text-amber-700' => $s['waiting'] > 0, 'text-stone-500' => $s['waiting'] === 0])>
                    {{ trans_choice(':count waiting|:count waiting', $s['waiting']) }}
                </div>
            </div>

            <div class="{{ $card }}">
                <div class="flex items-center gap-2 text-sm text-stone-500">
                    <flux:icon.heart class="size-4 text-orange-600" />
                    {{ __('Hearts') }}
                </div>
                <div class="mt-2 text-3xl font-bold">{{ $s['hearts'] }}</div>
                <div class="mt-1 text-sm text-stone-500">{{ __('Customers who saved your shop or dishes') }}</div>
            </div>
        </div>

        {{-- Getting started (disappears when all done) --}}
        @if ($this->stepsLeft > 0)
            @php
                $done = count($this->steps) - $this->stepsLeft;
            @endphp
            <div class="{{ $card }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ __('Getting started') }}</h2>
                    <span class="text-sm text-stone-500">{{ __(':done of :total done', ['done' => $done, 'total' => count($this->steps)]) }}</span>
                </div>
                <div class="mt-3 h-2 rounded-full bg-stone-100">
                    <div class="h-2 rounded-full bg-orange-500" style="width: {{ round($done / count($this->steps) * 100) }}%"></div>
                </div>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($this->steps as $step)
                        <li class="flex items-center gap-3 rounded-xl bg-stone-50 px-3 py-2.5">
                            @if ($step['done'])
                                <flux:icon.check-circle class="size-5 text-green-500" />
                                <span class="text-stone-400 line-through">{{ $step['label'] }}</span>
                            @else
                                <flux:icon.minus-circle class="size-5 text-stone-400" />
                                <a href="{{ route($step['route']) }}" wire:navigate class="font-medium text-orange-700 hover:underline">{{ $step['label'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Today's board + share --}}
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="{{ $card }} space-y-5 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ __('Today\'s board') }}</h2>
                    <span class="text-sm text-stone-500">
                        {{ __(':set of :total dishes set today', ['set' => $s['set'], 'total' => $s['dishes']]) }}
                        · {{ __(':count of :max specials', ['count' => $s['specials'], 'max' => ShopBoard::MAX_SPECIALS]) }}
                    </span>
                </div>

                <div class="h-2 rounded-full bg-stone-100">
                    <div class="h-2 rounded-full bg-green-500" style="width: {{ $setPercent }}%"></div>
                </div>

                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-green-50 p-3 ring-1 ring-green-100">
                        <div class="text-2xl font-bold text-green-700">{{ $s['available'] }}</div>
                        <div class="text-xs text-green-700">{{ __('Available') }}</div>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3 ring-1 ring-amber-100">
                        <div class="text-2xl font-bold text-amber-700">{{ $s['low'] }}</div>
                        <div class="text-xs text-amber-700">{{ __('Low') }}</div>
                    </div>
                    <div class="rounded-xl bg-red-50 p-3 ring-1 ring-red-100">
                        <div class="text-2xl font-bold text-red-700">{{ $s['soldOut'] }}</div>
                        <div class="text-xs text-red-700">{{ __('Sold out') }}</div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-stone-700">⭐ {{ __("Today's specials") }}</h3>
                    @if ($s['specialItems']->isEmpty())
                        <p class="mt-2 text-sm text-stone-500">{{ __('No specials yet. Star up to 3 dishes on the Today page.') }}</p>
                    @else
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($s['specialItems'] as $item)
                                <span class="inline-flex items-center gap-2 rounded-full bg-orange-50 py-1 pl-1 pr-3 text-sm text-orange-900 ring-1 ring-orange-100">
                                    @if ($item->photoUrl())
                                        <img src="{{ $item->photoUrl() }}" alt="" class="size-7 rounded-full object-cover">
                                    @endif
                                    {{ $item->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($s['soldOutItems']->isNotEmpty())
                    <div>
                        <h3 class="text-sm font-semibold text-stone-700">{{ __('Sold out today') }}</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($s['soldOutItems'] as $item)
                                <span class="rounded-full bg-stone-100 px-3 py-1 text-sm text-stone-500 line-through">{{ $item->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="{{ $card }} flex flex-col">
                <h2 class="text-lg font-semibold">{{ __('Share your board') }}</h2>
                <p class="mt-1 text-sm text-stone-500">{{ __('Customers scan this or open the link to see today\'s board.') }}</p>

                <img src="{{ route('shop.qr.png') }}" alt="{{ __('QR code') }}" class="mx-auto mt-4 size-36 rounded-xl ring-1 ring-stone-200">

                <div class="mt-4 flex items-center gap-2 rounded-xl bg-stone-50 p-2 ring-1 ring-stone-200" x-data="{ copied: false }">
                    <span class="min-w-0 flex-1 truncate px-1 text-xs text-stone-600">{{ route('board.show', $shop) }}</span>
                    <button type="button"
                            x-on:click="navigator.clipboard.writeText(@js(route('board.show', $shop))); copied = true; setTimeout(() => copied = false, 2000)"
                            class="shrink-0 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-orange-700 shadow-sm ring-1 ring-stone-200 hover:bg-orange-50">
                        <span x-show="! copied">{{ __('Copy link') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                    </button>
                </div>

                <div class="mt-auto flex gap-2 pt-4">
                    <a href="{{ route('shop.qr.png') }}" download class="flex-1 rounded-xl bg-orange-600 px-3 py-2.5 text-center text-sm font-semibold text-white hover:bg-orange-700">{{ __('Download QR') }}</a>
                    <a href="{{ route('shop.table-tent') }}" target="_blank" class="flex-1 rounded-xl bg-white px-3 py-2.5 text-center text-sm font-semibold text-stone-700 ring-1 ring-stone-200 hover:bg-stone-50">{{ __('Print your table card') }}</a>
                </div>
            </div>
        </div>

        {{-- Score + streak --}}
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="{{ $card }} lg:col-span-2">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ __('Kh-aaja score') }}</h2>
                        <p class="text-sm text-stone-500">{{ __('Shops with a higher score are shown first on the homepage.') }}</p>
                    </div>
                    <div class="shrink-0 text-4xl font-bold">
                        {{ $s['score']['total'] }}<span class="text-base font-normal text-stone-400">/100</span>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach ($s['score']['parts'] as $part => $points)
                        @php
                            $max = KhaajaScore::PARTS[$part];
                        @endphp
                        <div>
                            <div class="flex justify-between text-sm">
                                <span>{{ KhaajaScore::label($part) }}</span>
                                <span class="text-stone-500">{{ $points }}/{{ $max }}</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-stone-100">
                                <div @class(['h-2 rounded-full', 'bg-green-500' => $points >= $max * 0.7, 'bg-amber-500' => $points < $max * 0.7])
                                     style="width: {{ round($points / $max * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($s['score']['tip'])
                    <div class="mt-5 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-100">
                        💡 {{ $s['score']['tip'] }}
                    </div>
                @endif

                <p class="mt-3 text-xs text-stone-500">{{ __('The Pro plan never changes your score or your place on the homepage.') }}</p>
            </div>

            <div class="{{ $card }} space-y-3">
                <h2 class="text-lg font-semibold">{{ __('Your streak') }}</h2>

                <div class="flex items-baseline gap-2">
                    <flux:icon.fire @class(['size-8', 'text-orange-500' => $s['streak'] > 0, 'text-stone-300' => $s['streak'] === 0]) />
                    <span class="text-4xl font-bold">{{ $s['streak'] }}</span>
                </div>
                <p class="text-sm text-stone-500">{{ __(':count-day streak', ['count' => $s['streak']]) }}</p>

                @if ($s['reliable'])
                    <flux:badge color="green" icon="check-badge">{{ __('Reliable board') }}</flux:badge>
                @else
                    @php
                        $percent = min(100, (int) round($s['updatedDays'] / ShopBoard::RELIABLE_WINDOW_DAYS * 100));
                        $target = (int) round(ShopBoard::RELIABLE_MIN_DAYS / ShopBoard::RELIABLE_WINDOW_DAYS * 100);
                    @endphp

                    <div class="relative h-2 w-full rounded-full bg-stone-100">
                        <div class="h-2 rounded-full bg-green-500" style="width: {{ $percent }}%"></div>
                        <div class="absolute -top-1 h-4 w-0.5 bg-stone-500" style="left: {{ $target }}%" title="{{ __('Reliable board') }}"></div>
                    </div>
                    <p class="text-sm text-stone-500">
                        {{ __(':days of :min days for the Reliable board badge', ['days' => $s['updatedDays'], 'min' => ShopBoard::RELIABLE_MIN_DAYS]) }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Reservations + loved dishes --}}
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="{{ $card }} lg:col-span-2">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ __('Latest reservations') }}</h2>
                    <a href="{{ route('shop.reservations') }}" wire:navigate class="text-sm font-semibold text-orange-700 hover:underline">{{ __('See all') }} →</a>
                </div>

                @forelse ($this->latestReservations as $r)
                    @php
                        $pill = match ($r->status) {
                            ReservationStatus::Pending => 'bg-amber-100 text-amber-800',
                            ReservationStatus::Confirmed => 'bg-green-100 text-green-800',
                            ReservationStatus::Declined => 'bg-red-100 text-red-800',
                            default => 'bg-stone-100 text-stone-600',
                        };
                    @endphp
                    <div wire:key="latest-{{ $r->id }}" class="flex items-center gap-3 border-b border-stone-100 py-3 last:border-0">
                        @if ($r->item->photoUrl())
                            <img src="{{ $r->item->photoUrl() }}" alt="" class="size-10 shrink-0 rounded-lg object-cover">
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ __(':qty × :dish', ['qty' => $r->quantity, 'dish' => $r->item->name]) }}</div>
                            <div class="truncate text-sm text-stone-500">{{ $r->customer_name ?? '—' }} · {{ $r->created_at?->diffForHumans() }}</div>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pill }}">
                            {{ $r->status === ReservationStatus::Pending ? __('New') : $r->status->label() }}
                        </span>
                    </div>
                @empty
                    <p class="mt-4 rounded-xl border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">{{ __('No reservations yet today.') }}</p>
                @endforelse
            </div>

            <div class="{{ $card }}">
                <h2 class="text-lg font-semibold">{{ __('Most loved dishes') }}</h2>

                @forelse ($this->lovedDishes as $item)
                    <div wire:key="loved-{{ $item->id }}" class="flex items-center gap-3 border-b border-stone-100 py-3 last:border-0">
                        <span class="w-5 text-center text-sm font-bold text-stone-400">{{ $loop->iteration }}</span>
                        @if ($item->photoUrl())
                            <img src="{{ $item->photoUrl() }}" alt="" class="size-10 shrink-0 rounded-lg object-cover">
                        @endif
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $item->name }}</span>
                        <span class="shrink-0 text-sm font-semibold text-orange-700">♥ {{ $item->favourites_count }}</span>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-stone-500">{{ __('No hearts yet. Put your QR code on the tables so customers can find and save you.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Quick links --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <a href="{{ route('shop.menu') }}" wire:navigate class="{{ $card }} flex items-center gap-3 !p-4 hover:ring-orange-300">
                <span class="flex size-10 items-center justify-center rounded-xl bg-orange-50 text-orange-600"><flux:icon.book-open class="size-5" /></span>
                <div>
                    <div class="font-medium">{{ __('Menu') }}</div>
                    <div class="text-sm text-stone-500">{{ trans_choice(':count dish|:count dishes', $s['dishes']) }}</div>
                </div>
            </a>
            <a href="{{ route('shop.qr') }}" wire:navigate class="{{ $card }} flex items-center gap-3 !p-4 hover:ring-orange-300">
                <span class="flex size-10 items-center justify-center rounded-xl bg-orange-50 text-orange-600"><flux:icon.qr-code class="size-5" /></span>
                <div>
                    <div class="font-medium">{{ __('QR code') }}</div>
                    <div class="text-sm text-stone-500">{{ __('Print your table card') }}</div>
                </div>
            </a>
            <a href="{{ route('shop.edit') }}" wire:navigate class="{{ $card }} flex items-center gap-3 !p-4 hover:ring-orange-300">
                <span class="flex size-10 items-center justify-center rounded-xl bg-orange-50 text-orange-600"><flux:icon.building-storefront class="size-5" /></span>
                <div>
                    <div class="font-medium">{{ __('My shop') }}</div>
                    <div class="text-sm text-stone-500">{{ __('Hours, phone, WhatsApp') }}</div>
                </div>
            </a>
        </div>
    @else
        {{-- Admin and anyone else --}}
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 to-orange-700 p-6 text-white shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold sm:text-3xl">{{ __('Namaste, :name', ['name' => $this->firstName]) }}</h1>
            <p class="mt-1 text-orange-100">{{ __('Welcome to Kh-aaja.') }}</p>
        </div>

        @role('admin')
            @php
                $p = $this->platform;
            @endphp
            <h2 class="text-lg font-semibold">{{ __('Kh-aaja today') }}</h2>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ([
                    ['label' => __('Active shops'), 'value' => $p['shops'], 'icon' => 'building-storefront'],
                    ['label' => __('Boards updated today'), 'value' => $p['updated'], 'icon' => 'clipboard-document-check'],
                    ['label' => __('Reservations today'), 'value' => $p['reservations'], 'icon' => 'inbox'],
                    ['label' => __('Pro shops'), 'value' => $p['pro'], 'icon' => 'sparkles'],
                ] as $stat)
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                        <div class="flex items-center gap-2 text-sm text-stone-500">
                            <flux:icon :icon="$stat['icon']" class="size-4 text-orange-600" />
                            {{ $stat['label'] }}
                        </div>
                        <div class="mt-2 text-3xl font-bold">{{ $stat['value'] }}</div>
                    </div>
                @endforeach
            </div>

            <flux:button variant="primary" icon="building-office-2" :href="route('admin.shops')" wire:navigate>
                {{ __('Manage shops') }}
            </flux:button>
        @endrole
    @endif
</section>