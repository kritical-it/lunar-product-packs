<?php

namespace KriticalIt\ProductPacks\Discounts;

use KriticalIt\ProductPacks\Models\Pack;

/**
 * The complete packs of one pack found in a cart.
 */
final class PackMatch
{
    /**
     * @param  array<int, int>  $units  line id → units of it that carry the discount
     */
    public function __construct(
        public Pack $pack,
        public int $count,
        public array $units,
    ) {}
}
