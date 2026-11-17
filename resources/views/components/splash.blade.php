{{-- Brand splash: once per browser tab, about 3 seconds, tap to skip. --}}
<script>
    try {
        if (! sessionStorage.getItem('khaaja-splash')) {
            document.documentElement.classList.add('show-splash');
            sessionStorage.setItem('khaaja-splash', '1');
        }
    } catch (e) {}
</script>

<style>
    #khaaja-splash { display: none; }
    .show-splash #khaaja-splash { display: flex; }
    .show-splash body { overflow: hidden; }
    #khaaja-splash.leaving { opacity: 0; visibility: hidden; transition: opacity .5s ease, visibility .5s; }
    @keyframes khaaja-pop { 0% { transform: scale(.6) translateY(14px); opacity: 0; } 60% { transform: scale(1.08); opacity: 1; } 100% { transform: scale(1); } }
    @keyframes khaaja-rise { from { transform: translateY(10px); opacity: 0; } to { transform: none; opacity: 1; } }
    @keyframes khaaja-fill { from { width: 0; } to { width: 100%; } }
    #khaaja-splash .splash-pin { animation: khaaja-pop .8s cubic-bezier(.2, .9, .3, 1.3) both; }
    #khaaja-splash .splash-name { animation: khaaja-rise .6s .45s ease-out both; }
    #khaaja-splash .splash-tag { animation: khaaja-rise .6s .7s ease-out both; }
    #khaaja-splash .splash-bar i { animation: khaaja-fill 2.3s .2s linear both; }
    @media (prefers-reduced-motion: reduce) { #khaaja-splash * { animation: none !important; } }
</style>

<div id="khaaja-splash" role="status" aria-label="{{ __('Kh-aaja') }}"
     class="fixed inset-0 z-[100] cursor-pointer flex-col items-center justify-center bg-gradient-to-b from-orange-50 to-white px-6 text-center">
    <x-logo-mark class="splash-pin size-28 drop-shadow-lg" />
    <div class="splash-name mt-5 text-4xl font-bold tracking-tight text-stone-900">{{ __('Kh-aaja') }}</div>
    <div class="splash-tag mt-1 text-lg text-stone-500">{{ app()->isLocale('ne') ? 'Kh-aaja' : 'खाजा आज' }}</div>
    <p class="splash-tag mt-4 text-sm text-stone-500">{{ __('Live boards from local khaja shops, updated today.') }}</p>
    <div class="splash-bar mt-8 h-1 w-40 overflow-hidden rounded-full bg-orange-100">
        <i class="block h-full bg-orange-500"></i>
    </div>
</div>

<script>
    (function () {
        var splash = document.getElementById('khaaja-splash');

        if (! splash) {
            return;
        }

        if (! document.documentElement.classList.contains('show-splash')) {
            splash.remove();
            return;
        }

        var hide = function () { splash.classList.add('leaving'); };

        setTimeout(hide, 2500);
        splash.addEventListener('click', hide);
        splash.addEventListener('transitionend', function () {
            splash.remove();
            document.documentElement.classList.remove('show-splash');
        });
    })();
</script>