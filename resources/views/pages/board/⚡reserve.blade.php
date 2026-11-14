<?php

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Shop;
use App\Services\ShopBoard;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::public')] class extends Component {
    public const MAX_PER_IP = 5;

    public const WINDOW_SECONDS = 600;

    #[Locked]
    public Shop $shop;

    #[Locked]
    public Item $item;

    public string $name = '';
    public string $phone = '';
    public string $quantity = '1';
    public string $note = '';
    public bool $consent = false;

    /** Honeypot: hidden from people; spam bots fill it in. */
    public string $website = '';

    public function mount(Shop $shop, Item $item): void
    {
        abort_if($shop->isSuspended() || $item->is_archived, 404);

        $this->shop = $shop;
        $this->item = $item;
    }

    #[Computed]
    public function todayStatus(): ?ItemStatus
    {
        return (new ShopBoard($this->shop))->statuses()->get($this->item->id)?->status;
    }

    #[Computed]
    public function canReserve(): bool
    {
        return in_array($this->todayStatus, [ItemStatus::Available, ItemStatus::Low], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'regex:/^9[678]\d{8}$/'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'note' => ['nullable', 'string', 'max:200'],
            'consent' => ['accepted'],
        ];
    }

    public function reserve(): void
    {
        // A bot filled the invisible field: quietly send it back, save nothing.
        if ($this->website !== '') {
            $this->redirectRoute('board.show', $this->shop);

            return;
        }

        $key = 'reserve:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_IP)) {
            $this->addError('phone', __('Too many reservations. Please try again in a few minutes.'));

            return;
        }

        $validated = $this->validate();

        // The owner may have marked it sold out while the customer was typing.
        unset($this->todayStatus, $this->canReserve);

        if (! $this->canReserve) {
            $this->addError('quantity', __('Sorry, this dish is no longer available today.'));

            return;
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        $reservation = $this->shop->reservations()->make([
            'board_date' => (new ShopBoard($this->shop))->date(),
            'customer_name' => $validated['name'],
            'customer_phone' => $validated['phone'],
            'quantity' => (int) $validated['quantity'],
            'note' => $validated['note'] ?: null,
            'consent_at' => now(),
        ]);
        $reservation->item()->associate($this->item);
        $reservation->save();

        $this->redirectRoute('reservation.show', $reservation->public_token);
    }
}; ?>

<div class="mx-auto min-h-screen w-full max-w-xl px-4 pb-16 pt-6">
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('board.show', $shop) }}" class="text-sm underline underline-offset-4">← {{ $shop->name }}</a>
        <x-language-switch />
    </div>

    <div class="mt-6 flex items-center gap-3">
        @if ($item->photoUrl())
            <img src="{{ $item->photoUrl() }}" alt="" class="size-16 shrink-0 rounded-xl object-cover">
        @endif
        <div>
            <flux:heading size="xl" level="1">{{ __('Reserve :dish', ['dish' => $item->name]) }}</flux:heading>
            <flux:text>{{ $shop->name }} · {{ $shop->area }}</flux:text>
        </div>
    </div>

    @if (! $this->canReserve)
        <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            {{ __('This dish cannot be reserved right now. Please call the shop instead.') }}
        </div>
        <flux:button class="mt-4" icon="phone" :href="'tel:'.$shop->phone">{{ __('Call') }}</flux:button>
    @else
        <form wire:submit="reserve" class="mt-6 space-y-5">
            <flux:input wire:model="name" :label="__('Your name')" maxlength="80" required autocomplete="name" />
            <flux:input wire:model="phone" :label="__('Mobile number')" type="tel" inputmode="numeric" placeholder="98XXXXXXXX" required autocomplete="tel" />
            <flux:input wire:model="quantity" :label="__('How many plates?')" type="number" inputmode="numeric" min="1" max="20" required />
            <flux:textarea wire:model="note" :label="__('Note for the shop (optional)')" rows="2" maxlength="200" :placeholder="__('e.g. I will pick up at 1 pm')" />

            {{-- Honeypot: invisible to people and screen readers --}}
            <div class="hidden" aria-hidden="true">
                <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <flux:checkbox wire:model="consent" :label="__('Your name and number go only to :shop, and are deleted after 30 days.', ['shop' => $shop->name])" />
                <flux:error name="consent" />
            </div>

            <flux:button type="submit" variant="primary" class="w-full">{{ __('Send reservation') }}</flux:button>
            <flux:text size="sm">{{ __('The shop will confirm or decline. You will see the answer on the next page.') }}</flux:text>
        </form>
    @endif
</div>