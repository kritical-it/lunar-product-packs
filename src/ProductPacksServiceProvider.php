<?php

namespace KriticalIt\ProductPacks;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use KriticalIt\ProductPacks\Console\ImportCrossSellPacks;
use KriticalIt\ProductPacks\Console\InstallPackDiscount;
use KriticalIt\ProductPacks\Discounts\CartPackDiscounts;
use KriticalIt\ProductPacks\Discounts\PackDiscount;
use KriticalIt\ProductPacks\Discounts\PackDiscountRecord;
use KriticalIt\ProductPacks\Events\PackChanged;
use KriticalIt\ProductPacks\Models\PackItem;
use KriticalIt\ProductPacks\Observers\PackChanges;
use Lunar\Facades\ModelManifest;
use Lunar\Models\Channel;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Product;

class ProductPacksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/product-packs.php', 'lunar.product-packs');

        $this->app->scoped(CartPackDiscounts::class);
        $this->app->scoped(PackDiscountRecord::class);
    }

    public function boot(): void
    {
        // Also with the plugin turned off: a shop runs it from a migration,
        // which must not fail in the environments that turn the plugin off.
        if ($this->app->runningInConsole()) {
            $this->commands([InstallPackDiscount::class]);
        }

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

        $this->registerDiscount();

        if ($this->app->runningInConsole()) {
            $this->commands([ImportCrossSellPacks::class]);
        }
    }

    private function registerDiscount(): void
    {
        // Not added with Discounts::addType: the type stays out of the panel,
        // which only uses that list (Lunar applies a record by its class).

        // Priority 0 runs it after every other discount, so it adds to a coupon
        // instead of making AmountOff skip or shrink it: forced on any record
        // of this type, made by the command, the panel or a type change.
        Discount::modelClass()::saving(function ($discount) {
            if (is_a((string) $discount->type, PackDiscount::class, true)) {
                $discount->priority = 0;
            }
        });

        // The packs loaded for this request are stale once one changes.
        Event::listen(PackChanged::class, fn () => $this->app->make(CartPackDiscounts::class)->forget());

        // The packs with a discount depend on the record of the discount; not
        // on a use counted by an order (Lunar CreateOrder saves the record on
        // every draft), unless it has a limit of uses.
        $forgetRecord = function ($discount) {
            $changes = array_diff(array_keys($discount->getChanges()), ['uses', 'updated_at']);
            if ($discount->exists && $discount->wasChanged('uses') && $changes === [] && $discount->max_uses === null) {
                return;
            }

            $this->app->make(PackDiscountRecord::class)->forget();
            $this->app->make(CartPackDiscounts::class)->forget();
        };
        Discount::modelClass()::saved($forgetRecord);
        Discount::modelClass()::deleted($forgetRecord);

        // A channel or customer group created later gets the discount too.
        Channel::modelClass()::created(fn ($channel) => PackDiscount::records()
            ->each(fn ($discount) => PackDiscount::makeAvailable($discount, channels: collect([$channel]), groups: collect())));
        CustomerGroup::modelClass()::created(fn ($group) => PackDiscount::records()
            ->each(fn ($discount) => PackDiscount::makeAvailable($discount, channels: collect(), groups: collect([$group]))));
    }
}
