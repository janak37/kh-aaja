<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public')] #[Title('Privacy')] class extends Component {
    //
}; ?>

<div class="mx-auto min-h-screen w-full max-w-2xl px-4 pb-16 pt-5">
    <header class="flex items-center justify-between gap-3">
        <a href="{{ route('home') }}" class="text-lg font-bold">
            Kh-aaja <span class="font-normal text-zinc-500">· खाजा आज</span>
        </a>
        <x-language-switch />
    </header>

    <article class="mt-8 space-y-4 leading-relaxed [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:pt-4 [&_h2]:text-lg [&_h2]:font-semibold [&_ul]:ml-5 [&_ul]:list-disc [&_ul]:space-y-1.5">
        @include(app()->getLocale() === 'ne' ? 'privacy.ne' : 'privacy.en', [
            'days' => \App\Console\Commands\PurgeReservationDetails::AFTER_DAYS,
            'contact' => config('mail.from.address'),
        ])
    </article>
</div>