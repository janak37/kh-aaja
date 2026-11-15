<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::public')] class extends Component {
    #[Locked]
    public Reservation $reservation;

    public function mount(Reservation $reservation): void
    {
        $reservation->loadMissing('shop', 'item');

        abort_if($reservation->statusLinkExpired(), 404);

        $this->reservation = $reservation;
    }
}; ?>

@php
    $r = $reservation;
    $shop = $r->shop;
    $status = $r->status;
    $done = in_array($status, [ReservationStatus::Confirmed, ReservationStatus::Collected], true);
@endphp

<div @if ($status === ReservationStatus::Pending) wire:poll.15s.visible @endif class="mx-auto w-full max-w-xl px-4 py-8 sm:px-6 sm:py-12">
    <a href="{{ route('board.show', $shop) }}" class="text-sm font-medium text-stone-500 hover:text-orange-700">← {{ $shop->name }}</a>

    {{-- The answer, big and clear --}}
    <div @class([
        'mt-4 flex items-start gap-4 rounded-2xl border p-5 shadow-sm sm:p-6',
        'border-amber-300 bg-amber-50 text-amber-900' => $status === ReservationStatus::Pending,
        'border-green-300 bg-green-50 text-green-900' => $done,
        'border-red-300 bg-red-50 text-red-900' => $status === ReservationStatus::Declined,
        'border-stone-300 bg-stone-100 text-stone-700' => $status === ReservationStatus::Expired,
    ])>
        <span class="mt-0.5 shrink-0">
            @switch($status)
                @case(ReservationStatus::Pending)
                    <flux:icon.clock class="size-7 animate-pulse" />
                    @break
                @case(ReservationStatus::Declined)
                    <flux:icon.x-circle class="size-7" />
                    @break
                @case(ReservationStatus::Expired)
                    <flux:icon.minus-circle class="size-7" />
                    @break
                @default
                    <flux:icon.check-circle class="size-7" />
            @endswitch
        </span>

        <div>
            <div class="text-lg font-semibold sm:text-xl">
                @switch($status)
                    @case(ReservationStatus::Pending)
                        {{ __('Waiting for :shop to confirm', ['shop' => $shop->name]) }}
                        @break
                    @case(ReservationStatus::Confirmed)
                        {{ __('Confirmed! :shop is keeping it for you.', ['shop' => $shop->name]) }}
                        @break
                    @case(ReservationStatus::Declined)
                        {{ __('Declined: :reason', ['reason' => $r->decline_reason?->label() ?? __('Other reason')]) }}
                        @break
                    @case(ReservationStatus::Collected)
                        {{ __('Collected. Enjoy your food!') }}
                        @break
                    @default
                        {{ __('This reservation expired at the end of the day.') }}
                @endswitch
            </div>

            @if ($status === ReservationStatus::Pending)
                <div class="mt-1 text-sm">{{ __('This page updates by itself.') }}</div>
            @endif
        </div>
    </div>

    {{-- Details (never the phone number) --}}
    <dl class="mt-6 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
        <div class="flex justify-between gap-4 px-5 py-4">
            <dt class="text-stone-500">{{ __('Dish') }}</dt>
            <dd class="font-semibold">{{ $r->item->name }}</dd>
        </div>
        <div class="flex justify-between gap-4 px-5 py-4">
            <dt class="text-stone-500">{{ __('Quantity') }}</dt>
            <dd class="font-semibold">{{ $r->quantity }}</dd>
        </div>
        <div class="flex justify-between gap-4 px-5 py-4">
            <dt class="text-stone-500">{{ __('Date') }}</dt>
            <dd class="font-semibold">{{ $r->board_date->translatedFormat('l, j F') }}</dd>
        </div>
        @if ($r->customer_name)
            <div class="flex justify-between gap-4 px-5 py-4">
                <dt class="text-stone-500">{{ __('Name') }}</dt>
                <dd class="font-semibold">{{ $r->customer_name }}</dd>
            </div>
        @endif
        @if ($r->note)
            <div class="flex justify-between gap-4 px-5 py-4">
                <dt class="text-stone-500">{{ __('Note') }}</dt>
                <dd class="text-right">{{ $r->note }}</dd>
            </div>
        @endif
    </dl>

    <p class="mt-4 flex items-center gap-2 text-sm text-stone-500">
        <flux:icon.bookmark class="size-4" />
        {{ __('Save this page to check your reservation later.') }}
    </p>

    <div class="mt-6 flex gap-3">
        <a href="tel:{{ $shop->phone }}" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-orange-600 py-3 font-semibold text-white hover:bg-orange-700">
            <flux:icon.phone class="size-5" />
            {{ __('Call') }}
        </a>
        @if ($shop->whatsappUrl($r->item->name))
            <a href="{{ $shop->whatsappUrl($r->item->name) }}" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-green-600 py-3 font-semibold text-white hover:bg-green-700">
                <flux:icon.chat-bubble-left-right class="size-5" />
                {{ __('WhatsApp') }}
            </a>
        @endif
    </div>
</div>