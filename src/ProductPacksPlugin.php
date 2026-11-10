<?php

namespace KriticalIt\ProductPacks;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentIcon;
use KriticalIt\ProductPacks\Filament\Extensions\ProductPacksExtension;
use KriticalIt\ProductPacks\Filament\Resources\PackResource;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Support\Facades\LunarPanel;

/**
 * The packs in the Lunar panel: the «Packs» section and the tab on the
 * product.
 */
class ProductPacksPlugin implements Plugin
{
    public function getId(): string
    {
        return 'product-packs';
    }

    public function register(Panel $panel): void
    {
        if (! config('lunar.product-packs.enabled')) {
            return;
        }

        $panel->resources([PackResource::class]);

        LunarPanel::extensions([
            ProductResource::class => ProductPacksExtension::class,
        ]);

        FilamentIcon::register([
            'lunar::product-packs' => 'lucide-package',
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
