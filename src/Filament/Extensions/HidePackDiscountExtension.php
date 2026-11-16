<?php

namespace KriticalIt\ProductPacks\Filament\Extensions;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use KriticalIt\ProductPacks\Discounts\PackDiscount;
use Lunar\Admin\Support\Extending\ResourceExtension;

/**
 * The «Packs» discount stays out of the list of discounts: the plugin
 * manages it, and a change by hand would turn off the % of every pack.
 */
class HidePackDiscountExtension extends ResourceExtension
{
    public function extendTable(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query->where('type', '!=', PackDiscount::class));
    }
}
