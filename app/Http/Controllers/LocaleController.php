<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        $request->session()->put('locale', $locale);

        if ($user = Auth::user()) {
            $old = $user->locale;
            $user->forceFill(['locale' => $locale])->save();
            AuditLogger::record('user.locale.changed', $user, ['locale' => $old], ['locale' => $locale]);
        }

        return back();
    }
}
