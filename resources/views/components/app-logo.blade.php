@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="__('Kh-aaja')" {{ $attributes }}>
        <x-slot name="logo" class="flex size-8 items-center justify-center">
            <x-logo-mark class="size-8" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="__('Kh-aaja')" {{ $attributes }}>
        <x-slot name="logo" class="flex size-8 items-center justify-center">
            <x-logo-mark class="size-8" />
        </x-slot>
    </flux:brand>
@endif