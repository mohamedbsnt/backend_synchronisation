<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\Product;
use App\Observers\ProductObserver;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function boot(): void
    {
        // Enregistrer l'observateur Product pour synchronisation automatique
        if (class_exists(Product::class) && class_exists(ProductObserver::class)) {
            Product::observe(ProductObserver::class);
        }
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
