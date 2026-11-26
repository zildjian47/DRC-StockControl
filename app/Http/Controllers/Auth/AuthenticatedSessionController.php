<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user       = $request->user();
        $defaultUrl = route($user->homeRouteName(), absolute: false);

        if (!$user->isPpic()) {
            $intended = (string) $request->session()->get('url.intended', '');
            if ($intended !== '') {
                $path = (string) parse_url($intended, PHP_URL_PATH);
                $host = parse_url($intended, PHP_URL_HOST);

                $sameHost = empty($host) || strcasecmp($host, $request->getHost()) === 0;
                $isItemCard = preg_match('#^/item-card(?:/\d+|/scan)?/?$#', $path) === 1;

                if ($sameHost && $isItemCard) {
                    return redirect()->intended($defaultUrl);
                }
            }

            $request->session()->forget('url.intended');

            return redirect($defaultUrl);
        }

        return redirect()->intended($defaultUrl);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}