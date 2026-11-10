<?php

namespace KriticalIt\ProductPacks;

use Illuminate\Support\ServiceProvider;
use KriticalIt\ProductPacks\Console\ImportCrossSellPacks;
use KriticalIt\ProductPacks\Models\PackItem;
use KriticalIt\ProductPacks\Observers\PackChanges;
use Lunar\Facades\ModelManifest;
use Lunar\Models\Product;

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

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'product-packs');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'product-packs');

        if (! config('lunar.database.disable_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        Product::resolveRelationUsing('packItems', fn (Product $product) => $product->hasMany(PackItem::modelClass()));

        ModelManifest::addDirectory(__DIR__.'/Models');

        PackChanges::register();

        if ($this->app->runningInConsole()) {
            $this->commands([ImportCrossSellPacks::class]);
        }
    }
}
