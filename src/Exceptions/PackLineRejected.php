<?php

namespace Gongarce\ProductPacks\Exceptions;

use Lunar\Exceptions\Carts\CartException;
use Lunar\Models\ProductVariant;

/**
 * A line of a pack Lunar does not accept (its cart line validators), with
 * the variant and quantity of that line, so the shop can tell why.
 */
class PackLineRejected extends CartException
{
    public function __construct(
        CartException $previous,
        public readonly ProductVariant $variant,
        public readonly int $quantity,
    ) {
        parent::__construct($previous->errors());
    }

    /** Whether the variant can't be served at that quantity (stock), rather than a quantity rule. */
    public function isStock(): bool
    {
        return ! $this->variant->canBeFulfilledAtQuantity($this->quantity);
    }
}
