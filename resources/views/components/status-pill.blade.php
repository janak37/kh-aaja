@props(['status' => null])

@php
    use App\Enums\ItemStatus;

    $colours = match ($status) {
        ItemStatus::Available => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
        ItemStatus::Low => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        ItemStatus::SoldOut => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
        default => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
    };

    $dot = match ($status) {
        ItemStatus::Available => 'bg-green-500',
        ItemStatus::Low => 'bg-amber-500',
        ItemStatus::SoldOut => 'bg-red-500',
        default => 'bg-zinc-400',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium', $colours]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}"></span>
    {{ $status?->label() ?? __('Not updated') }}
</span>