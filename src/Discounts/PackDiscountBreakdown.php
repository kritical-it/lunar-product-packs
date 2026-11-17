<?php

namespace KriticalIt\ProductPacks\Discounts;

use Illuminate\Support\Collection;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdown;
use Lunar\DataTypes\Price;
use Lunar\Models\Contracts\Discount;

/**
 * The breakdown of a pack in a cart, with what each of its lines took off:
 * what the order records, without matching the packs again.
 */
class PackDiscountBreakdown extends DiscountBreakdown
{
    /**
     * @param  array<int, int>  $amounts  line id → cents without tax taken off, after the cap of the line
     */
    public function __construct(
        Price $price,
        Collection $lines,
        Discount $discount,
        public PackMatch $match,
        public array $amounts,
    ) {
        parent::__construct($price, $lines, $discount);
    }
}
