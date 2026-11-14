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
@endphp

<div @if ($status === ReservationStatus::Pending) wire:poll.15s.visible @endif class="mx-auto min-h-screen w-full max-w-xl px-4 pb-16 pt-6">
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('board.show', $shop) }}" class="text-sm underline underline-offset-4">← {{ $shop->name }}</a>
        <x-language-switch />
    </div>

    {{-- The answer, big and clear --}}
    <div @class([
        'mt-6 rounded-2xl border p-5',
        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200' => $status === ReservationStatus::Pending,
        'border-green-300 bg-green-50 text-green-900 dark:border-green-500/40 dark:bg-green-500/10 dark:text-green-200' => in_array($status, [ReservationStatus::Confirmed, ReservationStatus::Collected], true),
        'border-red-300 bg-red-50 text-red-900 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200' => $status === ReservationStatus::Declined,
        'border-zinc-300 bg-zinc-50 text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $status === ReservationStatus::Expired,
    ])>
        <div class="text-lg font-semibold">
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

    {{-- Details (never the phone number) --}}
    <dl class="mt-6 divide-y divide-zinc-200 rounded-2xl bg-white dark:divide-zinc-700 dark:bg-zinc-800">
        <div class="flex justify-between gap-4 p-4">
            <dt class="text-zinc-500">{{ __('Dish') }}</dt>
            <dd class="font-medium">{{ $r->item->name }}</dd>
        </div>
        <div class="flex justify-between gap-4 p-4">
            <dt class="text-zinc-500">{{ __('Quantity') }}</dt>
            <dd class="font-medium">{{ $r->quantity }}</dd>
        </div>
        <div class="flex justify-between gap-4 p-4">
            <dt class="text-zinc-500">{{ __('Date') }}</dt>
            <dd class="font-medium">{{ $r->board_date->translatedFormat('l, j F') }}</dd>
        </div>
        @if ($r->customer_name)
            <div class="flex justify-between gap-4 p-4">
                <dt class="text-zinc-500">{{ __('Name') }}</dt>
                <dd class="font-medium">{{ $r->customer_name }}</dd>
            </div>
        @endif
        @if ($r->note)
            <div class="flex justify-between gap-4 p-4">
                <dt class="text-zinc-500">{{ __('Note') }}</dt>
                <dd class="text-right">{{ $r->note }}</dd>
            </div>
        @endif
    </dl>

    <flux:text size="sm" class="mt-4">{{ __('Save this page to check your reservation later.') }}</flux:text>

    <div class="mt-6 flex gap-3">
        <a href="tel:{{ $shop->phone }}" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-zinc-900 py-3 font-medium text-white dark:bg-white dark:text-zinc-900">
            <flux:icon.phone class="size-5" />
            {{ __('Call') }}
        </a>
        @if ($shop->whatsappUrl($r->item->name))
            <a href="{{ $shop->whatsappUrl($r->item->name) }}" target="_blank" rel="noopener" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-green-600 py-3 font-medium text-white">
                <flux:icon.chat-bubble-left-right class="size-5" />
                {{ __('WhatsApp') }}
            </a>
        @endif
    </div>
</div>