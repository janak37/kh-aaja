<div {{ $attributes->class('inline-flex rounded-lg border border-zinc-200 p-0.5 text-xs font-medium dark:border-zinc-700') }}>
    @foreach (config('khaaja.locales') as $code => $label)
        <a
            href="{{ route('locale.switch', $code) }}"
            lang="{{ $code }}"
            @class([
                'rounded-md px-2.5 py-1 transition',
                'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => app()->getLocale() === $code,
                'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => app()->getLocale() !== $code,
            ])
        >
            {{ $label }}
        </a>
    @endforeach
</div>