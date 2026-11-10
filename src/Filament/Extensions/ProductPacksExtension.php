<?php

namespace KriticalIt\ProductPacks\Filament\Extensions;

use KriticalIt\ProductPacks\Filament\Resources\ProductResource\Pages\ManageProductPacks;
use Lunar\Admin\Support\Extending\ResourceExtension;

/**
 * The «Packs» tab on the product.
 */
class ProductPacksExtension extends ResourceExtension
{
    public function extendPages(array $pages): array
    {
        return [
            ...$pages,
            'packs' => ManageProductPacks::route('/{record}/packs'),
        ];
    }

    public function extendSubNavigation(array $nav): array
    {
        return [
            ...$nav,
            ManageProductPacks::class,
        ];
    }
}
