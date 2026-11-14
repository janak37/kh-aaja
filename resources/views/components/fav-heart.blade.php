@props(['shop' => null, 'item' => null])

@php
    $saved = $shop
        ? '$store.fav.hasShop('.$shop->id.')'
        : '$store.fav.hasDish('.\Illuminate\Support\Js::from($item->dish_key).')';

    $toggle = $shop
        ? '$wire.favourite(\'shop\', ...$store.fav.toggleShop('.$shop->id.'))'
        : '$wire.favourite(\'dish\', ...$store.fav.toggleDish('.\Illuminate\Support\Js::from($item->dish_key).', '.$item->id.'))';
@endphp

<button type="button"
    {{ $attributes->class('inline-flex shrink-0 items-center justify-center rounded-full p-1.5 transition hover:bg-zinc-100 dark:hover:bg-zinc-800') }}
    x-data
    x-on:click.prevent.stop="{{ $toggle }}"
    x-bind:aria-pressed="({{ $saved }}) ? 'true' : 'false'"
    aria-label="{{ __('Save to favourites') }}"
    title="{{ __('Save to favourites') }}">
    <flux:icon.heart variant="solid" class="size-5 text-red-500" x-show="{{ $saved }}" x-cloak />
    <flux:icon.heart class="size-5 text-zinc-400" x-show="! ({{ $saved }})" />
</button>