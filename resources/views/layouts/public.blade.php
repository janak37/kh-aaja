<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" style="color-scheme: light">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="theme-color" content="#ea580c" />

        <title>{{ isset($title) && $title !== 'Kh-aaja' ? __($title).' · '.__('Kh-aaja') : __('Kh-aaja') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- No @fluxAppearance here: customer pages are always light. --}}
    </head>
    <body class="flex min-h-screen flex-col bg-stone-50 text-stone-900 antialiased">
         <x-splash />
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b border-stone-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <x-logo-mark class="size-10" />
                    <span class="leading-tight">
                        <span class="block font-bold">{{ __('Kh-aaja') }}</span>
                        <span class="block text-xs text-stone-500">{{ app()->isLocale('ne') ? 'Kh-aaja' : 'खाजा आज' }}</span>
                    </span>
                </a>

                <nav class="flex items-center gap-1 sm:gap-2">
                    <a href="{{ route('favourites') }}" class="flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100" aria-label="{{ __('Favourites') }}">
                        <flux:icon.heart class="size-5 text-orange-600" />
                        <span class="hidden sm:inline">{{ __('Favourites') }}</span>
                    </a>

                    <x-language-switch />

                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-orange-600 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-700">{{ __('Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">{{ __('Log in') }}</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="border-t border-stone-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 pb-24 pt-6 text-xs text-stone-500 sm:flex-row sm:px-6">
                <span>© {{ date('Y') }} {{ __('Kh-aaja') }}</span>
                <span>
                    <a href="{{ route('privacy') }}" class="underline underline-offset-2 hover:text-stone-900">{{ __('Privacy') }}</a>
                    · {{ __('No ads. No tracking. Your favourites stay on your phone.') }}
                </span>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>