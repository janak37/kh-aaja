<?php

use App\Models\Shop;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My shop')] class extends Component {
    public bool $hasShop = false;

    public string $name = '';
    public string $category = '';
    public string $area = '';
    public string $phone = '';
    public string $whatsapp_number = '';
    public string $address = '';
    public string $opens_at = '';
    public string $closes_at = '';
    public string $description = '';

    /**
     * Load the owner's shop into the form, if they already have one.
     */
    public function mount(): void
    {
        $shop = Auth::user()->shop;

        if ($shop === null) {
            return;
        }

        $this->hasShop = true;
        $this->name = $shop->name;
        $this->category = $shop->category;
        $this->area = $shop->area;
        $this->phone = $shop->phone;
        $this->whatsapp_number = $shop->whatsapp_number ?? '';
        $this->address = $shop->address ?? '';
        $this->opens_at = substr($shop->opens_at ?? '', 0, 5);
        $this->closes_at = substr($shop->closes_at ?? '', 0, 5);
        $this->description = $shop->description ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $mobile = 'regex:/^9[678]\d{8}$/';

        return [
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(array_keys(config('khaaja.categories')))],
            'area' => ['required', Rule::in(config('khaaja.areas'))],
            'phone' => ['required', 'regex:/^(9[678]\d{8}|0\d{1,2}\d{6,7})$/'],
            'whatsapp_number' => ['nullable', $mobile],
            'address' => ['nullable', 'string', 'max:255'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Create the owner's shop the first time, update it after that.
     */
    public function save(): void
    {
        $data = collect($this->validate())
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all();

        $user = Auth::user();
        $shop = $user->shop;

        if ($shop === null) {
            $data['slug'] = $this->uniqueSlug($data['name']);
            $user->shop()->create($data);
            $this->hasShop = true;

            Flux::toast(variant: 'success', text: __('Your shop is ready.'));

            return;
        }

        // The slug never changes after creation, so printed QR codes keep working.
        $shop->update($data);

        Flux::toast(variant: 'success', text: __('Shop updated.'));
    }

    /**
     * "Didi Ko Khaja Ghar" -> "didi-ko-khaja-ghar", or "...-2" if taken.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $n = 2;

        while (Shop::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}; ?>

<section class="w-full max-w-2xl">
    <flux:heading size="xl" level="1">
        {{ $hasShop ? __('My shop') : __('Set up your shop') }}
    </flux:heading>
    <flux:subheading>
        {{ $hasShop
            ? __('Customers see these details on your board.')
            : __('Tell customers who you are. It takes about a minute.') }}
    </flux:subheading>

    <form wire:submit="save" class="my-6 w-full space-y-6">
        <flux:input wire:model="name" :label="__('Shop name')" type="text" maxlength="100" required autofocus />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:select wire:model="category" :label="__('Category')" :placeholder="__('Choose a category')" required>
                @foreach (config('khaaja.categories') as $value => $label)
                    <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="area" :label="__('Area')" :placeholder="__('Choose your area')" required>
                @foreach (config('khaaja.areas') as $area)
                    <flux:select.option :value="$area">{{ $area }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="phone" :label="__('Phone')" type="tel" inputmode="numeric" placeholder="98XXXXXXXX" required />
            <flux:input wire:model="whatsapp_number" :label="__('WhatsApp number (optional)')" type="tel" inputmode="numeric" placeholder="98XXXXXXXX" />
        </div>

        <flux:input wire:model="address" :label="__('Address (optional)')" type="text" maxlength="255" />

        <div class="grid grid-cols-2 gap-6">
            <flux:input wire:model="opens_at" :label="__('Opens at')" type="time" />
            <flux:input wire:model="closes_at" :label="__('Closes at')" type="time" />
        </div>

        <flux:textarea wire:model="description" :label="__('Short description (optional)')" rows="3" maxlength="500" />

        <flux:button variant="primary" type="submit" data-test="save-shop-button">
            {{ $hasShop ? __('Save') : __('Create my shop') }}
        </flux:button>
    </form>
</section>