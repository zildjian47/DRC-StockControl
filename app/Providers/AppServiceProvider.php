<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale((string) config('app.locale', 'id'));

        // Hanya paksa HTTPS pada HTTP request via Cloudflare/Ngrok proxy (tidak berjalan di CLI)
        if (!$this->app->runningInConsole() && request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}