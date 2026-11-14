<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Save the chosen language in a long-lived cookie and go back to the same page.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('khaaja.locales')), 404);

        // Only go "back" within our own site; anything else goes to the home page.
        $back = url()->previous();

        if (! str_starts_with($back, $request->getSchemeAndHttpHost())) {
            $back = route('home');
        }

        return redirect()->to($back)->withCookie(cookie()->forever('locale', $locale));
    }
}
