<?php

namespace Gongarce\ProductPacks;

use Illuminate\Support\ServiceProvider;

class ProductPacksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/product-packs.php', 'lunar.product-packs');
    }

    public function boot(): void
    {
        if (! config('lunar.product-packs.enabled')) {
            return;
        }

        if (! config('lunar.database.disable_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}
