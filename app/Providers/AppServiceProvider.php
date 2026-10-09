<?php

namespace App\Providers;

use App\Listeners\Projects\SyncCatalogCard;
use App\Listeners\Review\AgencyQueueSubscriber;
use App\Listeners\Review\ReviewNotificationSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* Behind Cloudflare the origin may be reached over http; every generated URL must still be https. */
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        Event::subscribe(ReviewNotificationSubscriber::class);
        Event::subscribe(SyncCatalogCard::class);
        Event::subscribe(AgencyQueueSubscriber::class);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->mixedCase()->numbers()->uncompromised()
            : Password::min(8));
    }
}
