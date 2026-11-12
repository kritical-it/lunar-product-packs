<?php

namespace KriticalIt\ProductPacks\Discounts;

/**
 * A line of a cart as the matcher sees it: no Lunar cart needed.
 */
final class MatchLine
{
    /**
     * @param  list<int>  $valueIds  ids of the ProductOptionValue of the variant
     */
    public function __construct(
        public int $id,
        public int $productId,
        public int $variantId,
        public array $valueIds,
        public int $quantity,
    ) {}
}
