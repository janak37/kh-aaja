<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        {{ $slot }}

        <footer class="mx-auto max-w-3xl px-4 pb-28 pt-2 text-center text-xs text-zinc-500">
            <a href="{{ route('privacy') }}" class="underline underline-offset-2 hover:text-zinc-900 dark:hover:text-white">{{ __('Privacy') }}</a>
            · {{ __('No ads. No tracking. Your favourites stay on your phone.') }}
        </footer>

        @fluxScripts
    </body>
</html>