<?php

namespace KriticalIt\ProductPacks\Exceptions;

use InvalidArgumentException;

/**
 * A line of a variant whose product the pack does not show: it was left out
 * of the pack, unpublished or deleted, or the variant is not of the pack.
 */
class PackProductNotAvailable extends InvalidArgumentException
{
    public function __construct(public readonly int $variantId, int $packId)
    {
        parent::__construct("Variant {$variantId} is not of a product pack {$packId} shows.");
    }
}
