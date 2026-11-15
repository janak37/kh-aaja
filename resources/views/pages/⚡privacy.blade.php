<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public')] #[Title('Privacy')] class extends Component {
    //
}; ?>

<div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
    <a href="{{ route('home') }}" class="text-sm font-medium text-stone-500 hover:text-orange-700">← {{ __('All shops') }}</a>

    <article class="mt-4 space-y-4 rounded-2xl bg-white p-6 leading-relaxed text-stone-700 shadow-sm ring-1 ring-stone-200 sm:p-10
        [&_h1]:text-3xl [&_h1]:font-bold [&_h1]:tracking-tight [&_h1]:text-stone-900
        [&_h2]:border-t [&_h2]:border-stone-100 [&_h2]:pt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-orange-700
        [&_ul]:ml-5 [&_ul]:list-disc [&_ul]:space-y-2 [&_li]:marker:text-orange-500
        [&_a]:font-medium [&_a]:text-orange-700">
        @include(app()->getLocale() === 'ne' ? 'privacy.ne' : 'privacy.en', [
            'days' => \App\Console\Commands\PurgeReservationDetails::AFTER_DAYS,
            'contact' => config('mail.from.address'),
        ])
    </article>
</div>