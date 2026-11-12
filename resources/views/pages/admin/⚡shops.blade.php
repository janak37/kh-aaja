<?php

use App\Enums\UserRole;
use App\Models\Shop;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Shops')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all';

    public function mount(): void
    {
        $this->ensureAdmin();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Shop>
     */
    #[Computed]
    public function shops(): LengthAwarePaginator
    {
        $weekAgo = now('Asia/Kathmandu')->subDays(6)->toDateString();

        return Shop::query()
            ->with('owner:id,name,email')
            ->withCount(['items' => fn ($q) => $q->where('is_archived', false)])
            ->withMax('boardUpdates as last_board_date', 'board_date')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('area', 'like', $term)
                    ->orWhereHas('owner', fn ($o) => $o
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)));
            })
            ->when($this->filter === 'pro', fn ($q) => $q->where('is_pro', true))
            ->when($this->filter === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($this->filter === 'stale', fn ($q) => $q->whereDoesntHave(
                'boardUpdates',
                fn ($b) => $b->where('board_date', '>=', $weekAgo),
            ))
            ->latest()
            ->paginate(20);
    }

    public function togglePro(int $id): void
    {
        $this->ensureAdmin();

        $shop = Shop::findOrFail($id);
        $shop->is_pro = ! $shop->is_pro;
        $shop->save();

        Flux::toast(variant: 'success', text: $shop->is_pro
            ? __(':shop is now Pro.', ['shop' => $shop->name])
            : __(':shop is no longer Pro.', ['shop' => $shop->name]));
    }

    public function toggleSuspended(int $id): void
    {
        $this->ensureAdmin();

        $shop = Shop::findOrFail($id);
        $shop->suspended_at = $shop->isSuspended() ? null : now();
        $shop->save();

        Flux::toast(variant: $shop->isSuspended() ? 'warning' : 'success', text: $shop->isSuspended()
            ? __(':shop is suspended. Its board is hidden.', ['shop' => $shop->name])
            : __(':shop is active again.', ['shop' => $shop->name]));
    }

    /**
     * Second lock behind the route: every action checks the role again.
     */
    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole(UserRole::Admin), 403);
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Shops') }}</flux:heading>
        <flux:subheading>{{ __('Every shop on Kh-aaja. Set Pro, or suspend a shop that breaks the rules.') }}</flux:subheading>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search shop, area, owner name or email')"
            />
        </div>
        <div class="sm:w-56">
            <flux:select wire:model.live="filter">
                <flux:select.option value="all">{{ __('All shops') }}</flux:select.option>
                <flux:select.option value="pro">{{ __('Pro') }}</flux:select.option>
                <flux:select.option value="suspended">{{ __('Suspended') }}</flux:select.option>
                <flux:select.option value="stale">{{ __('Not updated in 7 days') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">{{ __('Shop') }}</th>
                    <th class="px-4 py-3">{{ __('Owner') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('Dishes') }}</th>
                    <th class="px-4 py-3">{{ __('Last update') }}</th>
                    <th class="px-4 py-3">{{ __('Plan') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->shops as $shop)
                    @php
                        $last = $shop->last_board_date ? Carbon::parse($shop->last_board_date) : null;
                        $stale = $last === null || $last->lt(now('Asia/Kathmandu')->subDays(2)->startOfDay());
                    @endphp

                    <tr wire:key="admin-shop-{{ $shop->id }}" @class(['opacity-60' => $shop->isSuspended()])>
                        <td class="px-4 py-3">
                            <a href="{{ route('board.show', $shop) }}" target="_blank" class="font-medium underline-offset-4 hover:underline">
                                {{ $shop->name }}
                            </a>
                            <div class="text-xs text-zinc-500">{{ $shop->area }} · {{ $shop->categoryLabel() }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ $shop->owner->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $shop->owner->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">{{ $shop->items_count }}</td>
                        <td @class(['px-4 py-3', 'text-red-600 dark:text-red-400' => $stale])>
                            {{ $last ? $last->format('j M Y') : __('Never') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if ($shop->is_pro)
                                    <flux:badge color="violet" size="sm">{{ __('Pro') }}</flux:badge>
                                @endif
                                <flux:button size="xs" variant="ghost" wire:click="togglePro({{ $shop->id }})">
                                    {{ $shop->is_pro ? __('Remove Pro') : __('Make Pro') }}
                                </flux:button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if ($shop->isSuspended())
                                    <flux:badge color="red" size="sm">{{ __('Suspended') }}</flux:badge>
                                    <flux:button size="xs" wire:click="toggleSuspended({{ $shop->id }})">
                                        {{ __('Restore') }}
                                    </flux:button>
                                @else
                                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        wire:click="toggleSuspended({{ $shop->id }})"
                                        wire:confirm="{{ __('Suspend :shop? Its public board will be hidden until you restore it.', ['shop' => $shop->name]) }}"
                                    >
                                        {{ __('Suspend') }}
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">{{ __('No shops match.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->shops->links() }}
</section>