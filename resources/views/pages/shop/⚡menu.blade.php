<?php

use App\Actions\StoreDishPhoto;
use App\Models\Item;
use App\Models\Shop;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Menu')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?string $currentPhotoUrl = null;

    public bool $showArchived = false;

    public string $name = '';
    public string $description = '';
    public string $default_price = '';
    public bool $is_veg = false;
    public ?TemporaryUploadedFile $photo = null;

    /**
     * An owner must set up their shop before adding dishes.
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
        if ($this->shop === null) {
            return new Collection;
        }

        return $this->shop->items()->where('is_archived', $this->showArchived)->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('items', 'name')
                    ->where('shop_id', $this->ownShop()->id)
                    ->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:140'],
            'default_price' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_veg' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function save(StoreDishPhoto $storePhoto): void
    {
        $validated = $this->validate();
        $shop = $this->ownShop();

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'default_price' => $validated['default_price'] === '' || $validated['default_price'] === null
                ? null
                : (int) $validated['default_price'],
            'is_veg' => (bool) $validated['is_veg'],
        ];

        if ($this->editingId !== null) {
            $item = $this->ownItem($this->editingId);
            $item->update($data);
            $message = __('Dish updated.');
        } else {
            $item = $shop->items()->create($data + ['sort_order' => (int) $shop->items()->max('sort_order') + 1]);
            $message = __('Dish added.');
        }

        if ($this->photo !== null) {
            $oldPath = $item->photo_path;
            $item->update(['photo_path' => $storePhoto->handle($this->photo, $shop)]);

            if ($oldPath !== null) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        Flux::toast(variant: 'success', text: $message);
        $this->cancel();
    }

    public function edit(int $id): void
    {
        $item = $this->ownItem($id);

        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->description = $item->description ?? '';
        $this->default_price = $item->default_price === null ? '' : (string) $item->default_price;
        $this->is_veg = $item->is_veg;
        $this->photo = null;
        $this->currentPhotoUrl = $item->photoUrl();
        $this->resetValidation();
    }

    public function removePhoto(): void
    {
        if ($this->editingId === null) {
            $this->photo = null;

            return;
        }

        $item = $this->ownItem($this->editingId);

        if ($item->photo_path !== null) {
            Storage::disk('public')->delete($item->photo_path);
            $item->update(['photo_path' => null]);
        }

        $this->photo = null;
        $this->currentPhotoUrl = null;
        unset($this->items);
        Flux::toast(__('Photo removed.'));
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'currentPhotoUrl', 'name', 'description', 'default_price', 'is_veg', 'photo');
        $this->resetValidation();
        unset($this->items);
    }

    public function archive(int $id): void
    {
        $this->ownItem($id)->update(['is_archived' => true]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        unset($this->items);
        Flux::toast(__('Dish archived. Find it under "Show archived".'));
    }

    public function restore(int $id): void
    {
        $shop = $this->ownShop();

        $this->ownItem($id)->update([
            'is_archived' => false,
            'sort_order' => (int) $shop->items()->max('sort_order') + 1,
        ]);

        unset($this->items);
        Flux::toast(variant: 'success', text: __('Dish is back on the menu.'));
    }

    /**
     * Move a dish one place up (-1) or down (+1), then renumber 0, 1, 2…
     */
    public function move(int $id, int $direction): void
    {
        $items = $this->ownShop()->items()->active()->get()->values();
        $from = $items->search(fn (Item $item) => $item->id === $id);

        abort_if($from === false, 404);

        $to = $from + ($direction < 0 ? -1 : 1);

        if ($to < 0 || $to >= $items->count()) {
            return;
        }

        $moved = $items->splice($from, 1)->first();
        $items->splice($to, 0, [$moved]);

        $items->values()->each(function (Item $item, int $position): void {
            if ($item->sort_order !== $position) {
                $item->update(['sort_order' => $position]);
            }
        });

        unset($this->items);
    }

    private function ownShop(): Shop
    {
        $shop = $this->shop;

        abort_if($shop === null, 403);

        return $shop;
    }

    /**
     * Only ever finds dishes of the logged-in owner's shop; anything else is a 404.
     */
    private function ownItem(int $id): Item
    {
        return $this->ownShop()->items()->findOrFail($id);
    }
}; ?>

<section class="w-full max-w-3xl space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Menu') }}</flux:heading>
        <flux:subheading>{{ __('Add your dishes once. Each day you only mark what is available.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading size="lg">{{ $editingId ? __('Edit dish') : __('Add a dish') }}</flux:heading>

        <div class="flex flex-col gap-4 sm:flex-row">
            {{-- Photo --}}
            <div class="shrink-0 space-y-2">
                <div class="relative size-32 overflow-hidden rounded-xl border border-dashed border-zinc-300 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                    @if ($photo && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-full object-cover">
                    @elseif ($currentPhotoUrl)
                        <img src="{{ $currentPhotoUrl }}" alt="" class="size-full object-cover">
                    @else
                        <div class="flex size-full flex-col items-center justify-center gap-1 text-zinc-400">
                            <flux:icon.photo class="size-8" />
                            <span class="text-xs">{{ __('No photo') }}</span>
                        </div>
                    @endif

                    <div wire:loading wire:target="photo" class="absolute inset-0 flex items-center justify-center bg-black/50 text-xs text-white">
                        {{ __('Uploading…') }}
                    </div>
                </div>

                <label class="block cursor-pointer text-center text-sm font-medium text-zinc-700 underline dark:text-zinc-300">
                    {{ $photo || $currentPhotoUrl ? __('Change photo') : __('Add photo') }}
                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>

                @if ($photo || $currentPhotoUrl)
                    <button type="button" wire:click="removePhoto" class="block w-full text-center text-xs text-red-600 underline">
                        {{ __('Remove photo') }}
                    </button>
                @endif

                <flux:error name="photo" />
            </div>

            {{-- Details --}}
            <div class="flex-1 space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <flux:input wire:model="name" :label="__('Dish name')" maxlength="100" placeholder="Jhol Momo" required />
                    </div>
                    <flux:input wire:model="default_price" :label="__('Price (Rs)')" type="number" inputmode="numeric" min="1" placeholder="180" />
                </div>

                <flux:input wire:model="description" :label="__('Short description (optional)')" maxlength="140" />

                <flux:checkbox wire:model="is_veg" :label="__('Vegetarian')" />
            </div>
        </div>

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="photo,save" data-test="save-dish-button">
                {{ $editingId ? __('Save changes') : __('Add dish') }}
            </flux:button>

            @if ($editingId)
                <flux:button type="button" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            @endif
        </div>
    </form>

    <div class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ $showArchived ? __('Archived dishes') : __('Your dishes') }}</flux:heading>
            <flux:switch wire:model.live="showArchived" :label="__('Show archived')" />
        </div>

        @forelse ($this->items as $item)
            <div wire:key="item-{{ $item->id }}" class="flex items-center gap-3 rounded-xl border border-zinc-200 p-2 pe-3 dark:border-zinc-700">
                @if ($item->photoUrl())
                    <img src="{{ $item->photoUrl() }}" alt="{{ $item->name }}" loading="lazy" class="size-16 shrink-0 rounded-lg object-cover">
                @else
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-400 dark:bg-zinc-900">
                        <flux:icon.photo class="size-6" />
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate font-medium">{{ $item->name }}</span>
                        @if ($item->is_veg)
                            <flux:badge color="green" size="sm">{{ __('Veg') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="truncate">
                        {{ $item->default_price ? 'Rs '.number_format($item->default_price) : __('No fixed price') }}
                        @if ($item->description)
                            · {{ $item->description }}
                        @endif
                    </flux:text>
                </div>

                @if ($showArchived)
                    <flux:button size="sm" icon="arrow-uturn-left" wire:click="restore({{ $item->id }})">
                        {{ __('Restore') }}
                    </flux:button>
                @else
                    <div class="flex shrink-0 items-center">
                        <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="move({{ $item->id }}, -1)" :disabled="$loop->first" :aria-label="__('Move up')" />
                        <flux:button size="sm" variant="ghost" icon="arrow-down" wire:click="move({{ $item->id }}, 1)" :disabled="$loop->last" :aria-label="__('Move down')" />
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $item->id }})" :aria-label="__('Edit')" />
                        <flux:button size="sm" variant="ghost" icon="archive-box" wire:click="archive({{ $item->id }})" :aria-label="__('Archive')" />
                    </div>
                @endif
            </div>
        @empty
            <flux:text>
                {{ $showArchived ? __('No archived dishes.') : __('No dishes yet. Add your first one above.') }}
            </flux:text>
        @endforelse
    </div>
</section>