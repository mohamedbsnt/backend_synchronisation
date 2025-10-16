<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// ✅ Ces deux lignes doivent pointer vers les bons namespaces
use App\Models\Product;
use App\Observers\ProductObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Les événements à écouter dans l’application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Enregistrement des observateurs et événements.
     *
     * @return void
     */
    public function boot(): void
    {
        // ✅ Vérifie d'abord que la classe Product existe avant de l’observer
        if (class_exists(\App\Models\Product::class) && class_exists(\App\Observers\ProductObserver::class)) {
            Product::observe(ProductObserver::class);
        }
    }

    /**
     * Si les événements doivent être automatiquement découverts.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
