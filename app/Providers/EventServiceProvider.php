<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\Product;
use App\Observers\ProductObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     */
    protected $listen = [
        // Tes autres événements...
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Enregistre l'observer Product
        Product::observe(ProductObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
