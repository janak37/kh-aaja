<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" style="color-scheme: light">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gradient-to-b from-orange-50 to-stone-50 text-stone-900 antialiased">
        <x-splash />
        <div class="flex min-h-svh flex-col">
            {{-- Small top bar: back to the site, language --}}
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="text-sm font-medium text-stone-500 hover:text-orange-700">← {{ __('All shops') }}</a>
                <x-language-switch />
            </div>

            <div class="flex flex-1 flex-col items-center justify-center px-4 pb-16">
                <div class="w-full max-w-sm">
                    <a href="{{ route('home') }}" class="mb-6 flex flex-col items-center gap-2" wire:navigate>
                        <x-logo-mark class="size-14" />
                        <span class="text-center leading-tight">
                            <span class="block text-lg font-bold">Kh-aaja</span>
                            <span class="block text-xs text-stone-500">खाजा आज</span>
                        </span>
                    </a>

                    <div class="flex flex-col gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200 sm:p-8">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>

        @fluxScripts
    </body>
</html>