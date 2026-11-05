<?php

use App\Models\Shop;
use App\Services\QrCode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('QR code')] class extends Component {
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

    #[Computed]
    public function url(): string
    {
        return $this->shop === null ? '' : route('board.show', $this->shop);
    }

    #[Computed]
    public function svg(): string
    {
        return $this->shop === null ? '' : QrCode::svg($this->url, 280);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('QR code') }}</flux:heading>
        <flux:subheading>{{ __('Put this on your counter. Customers scan it to see today\'s board.') }}</flux:subheading>
    </div>

    @if ($this->shop)
        <div class="flex flex-col items-center gap-6 rounded-xl border border-zinc-200 p-6 sm:flex-row sm:items-start dark:border-zinc-700">
            <div class="size-56 shrink-0 rounded-xl bg-white p-3 [&_svg]:size-full">
                {!! $this->svg !!}
            </div>

            <div class="w-full space-y-4">
                <flux:input :label="__('Your board link')" :value="$this->url" readonly copyable />

                <div class="flex flex-wrap gap-2">
                    <flux:button icon="arrow-down-tray" :href="route('shop.qr.png')">{{ __('Download PNG') }}</flux:button>
                    <flux:button icon="printer" :href="route('shop.table-tent')" target="_blank">{{ __('Print table card (A5)') }}</flux:button>
                    <flux:button icon="eye" :href="$this->url" target="_blank">{{ __('Open board') }}</flux:button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="chat-bubble-left-right"
                        :href="'https://wa.me/?text='.rawurlencode(__('See what is available today at :shop: :url', ['shop' => $this->shop->name, 'url' => $this->url]))"
                        target="_blank"
                    >
                        {{ __('Share on WhatsApp') }}
                    </flux:button>

                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="share"
                        :href="'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($this->url)"
                        target="_blank"
                    >
                        {{ __('Share on Facebook') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</section>