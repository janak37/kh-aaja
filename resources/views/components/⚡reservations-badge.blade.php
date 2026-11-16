<?php

use App\Enums\ReservationStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    /** Remembered on page load, because refresh requests aren't on the Reservations URL. */
    #[Locked]
    public bool $current = false;

    public function mount(): void
    {
        $this->current = request()->routeIs('shop.reservations');
    }

    #[Computed]
    public function waiting(): int
    {
        return Auth::user()?->shop?->reservations()
            ->where('status', ReservationStatus::Pending)
            ->where('board_date', now('Asia/Kathmandu')->toDateString())
            ->count() ?? 0;
    }
}; ?>

<div wire:poll.20s.visible>
    <flux:sidebar.item icon="inbox" :href="route('shop.reservations')" :current="$current" :badge="$this->waiting > 0 ? $this->waiting : null" wire:navigate>
        {{ __('Reservations') }}
    </flux:sidebar.item>
</div>