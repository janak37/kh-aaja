<?php

use App\Enums\DeclineReason;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Shop;
use App\Services\ShopBoard;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reservations')] class extends Component {
    public bool $showWeek = false;

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
     * New first, then confirmed, then finished; newest first within each.
     *
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function reservations(): Collection
    {
        $shop = $this->shop;

        if ($shop === null) {
            return new Collection;
        }

        $from = $this->showWeek
            ? now($shop->timezone)->subDays(6)->toDateString()
            : (new ShopBoard($shop))->date();

        return $shop->reservations()
            ->with(['item', 'shop'])
            ->where('board_date', '>=', $from)
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 ELSE 2 END")
            ->orderByDesc('board_date')
            ->latest()
            ->get();
    }

    public function confirm(int $id): void
    {
        $this->change($id, fn (Reservation $r) => $r->confirm(), __('Reservation confirmed.'));
    }

    public function decline(int $id, string $reason): void
    {
        $case = DeclineReason::tryFrom($reason);

        abort_if($case === null, 422);

        $this->change($id, fn (Reservation $r) => $r->decline($case), __('Reservation declined.'));
    }

    public function collected(int $id): void
    {
        $this->change($id, fn (Reservation $r) => $r->markCollected(), __('Marked as collected.'));
    }

    /**
     * Only ever finds reservations of the logged-in owner's shop; anything else is a 404.
     */
    private function change(int $id, Closure $action, string $message): void
    {
        $shop = $this->shop;

        abort_if($shop === null, 403);

        $reservation = $shop->reservations()->findOrFail($id);

        try {
            $action($reservation);
        } catch (DomainException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
            unset($this->reservations);

            return;
        }

        Flux::toast(variant: 'success', text: $message);
        unset($this->reservations);
    }
}; ?>

<section wire:poll.20s.visible class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Reservations') }}</flux:heading>
            <flux:subheading>{{ __('Confirm or decline new reservations quickly. Customers see your answer straight away.') }}</flux:subheading>
        </div>
        <flux:switch wire:model.live="showWeek" :label="__('Show the last 7 days')" />
    </div>

    <div class="space-y-3">
        @forelse ($this->reservations as $r)
            @php
                $isNew = $r->status === ReservationStatus::Pending;
                $pill = match ($r->status) {
                    ReservationStatus::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                    ReservationStatus::Confirmed => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                    ReservationStatus::Declined => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                    default => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
                };
            @endphp

            <div wire:key="reservation-{{ $r->id }}" @class([
                'space-y-3 rounded-xl border p-4',
                'border-amber-400 bg-amber-50/50 dark:bg-amber-950/20' => $isNew,
                'border-zinc-200 dark:border-zinc-700' => ! $isNew,
            ])>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-lg font-semibold">{{ __(':qty × :dish', ['qty' => $r->quantity, 'dish' => $r->item->name]) }}</div>
                        <div class="text-sm text-zinc-500">
                            {{ $r->customer_name ?? '—' }}
                            · {{ $r->board_date->translatedFormat('j M') }}
                            · {{ $r->created_at?->diffForHumans() }}
                        </div>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pill }}">
                        {{ $isNew ? __('New') : $r->status->label() }}
                        @if ($r->decline_reason)
                            · {{ $r->decline_reason->label() }}
                        @endif
                    </span>
                </div>

                @if ($r->note)
                    <flux:text>“{{ $r->note }}”</flux:text>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    @if ($r->phoneVisibleToOwner())
                        <flux:button size="sm" icon="phone" :href="'tel:'.$r->customer_phone">{{ $r->customer_phone }}</flux:button>
                    @endif

                    @if ($isNew)
                        <flux:button size="sm" variant="primary" icon="check" wire:click="confirm({{ $r->id }})">
                            {{ __('Confirm') }}
                        </flux:button>
                    @endif

                    @if ($r->status === ReservationStatus::Confirmed)
                        <flux:button size="sm" variant="primary" icon="hand-thumb-up" wire:click="collected({{ $r->id }})">
                            {{ __('Mark collected') }}
                        </flux:button>
                    @endif

                    @if (in_array($r->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true))
                        <flux:dropdown>
                            <flux:button size="sm" variant="danger" icon-trailing="chevron-down">{{ __('Decline') }}</flux:button>
                            <flux:menu>
                                @foreach (DeclineReason::cases() as $reason)
                                    <flux:menu.item wire:click="decline({{ $r->id }}, '{{ $reason->value }}')">
                                        {{ $reason->label() }}
                                    </flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600">
                <flux:text>{{ __('No reservations today.') }}</flux:text>
            </div>
        @endforelse
    </div>
</section>