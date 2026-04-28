<?php

namespace App\Providers;

use App\Models\GpsDevice;
use App\Observers\GpsDeviceObserver;
use Illuminate\Support\ServiceProvider;

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
        GpsDevice::observe(GpsDeviceObserver::class);
    }
}
