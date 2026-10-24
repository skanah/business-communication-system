<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\ChannelInterface;
use App\Channels\SMSChannel;
use App\Channels\EmailChannel;
use App\Services\ChannelRegistry;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind channel implementations to their interfaces
        $this->app->bind('sms.channel', function ($app) {
            return $app->make(SMSChannel::class);
        });

        $this->app->bind('email.channel', function ($app) {
            return $app->make(EmailChannel::class);
        });

        // Register the channel registry as a singleton
        $this->app->singleton(ChannelRegistry::class, function ($app) {
            return new ChannelRegistry();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
