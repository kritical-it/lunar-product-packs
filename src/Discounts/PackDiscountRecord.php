<?php

namespace KriticalIt\ProductPacks\Discounts;

use Lunar\Models\Discount;

/**
 * Whether the cart can give the discount of the packs at all: a record of
 * type PackDiscount that is active and usable. Without one, the packs keep
 * their % but no page promises it and no cart applies it.
 *
 * Scoped: checked once per request; saving or deleting a discount forgets it.
 */
class PackDiscountRecord
{
    private ?bool $usable = null;

    public function usable(): bool
    {
        return $this->usable ??= Discount::modelClass()::query()
            ->where('type', PackDiscount::class)
            ->active()
            ->usable()
            ->exists();
    }

    public function forget(): void
    {
        $this->usable = null;
    }
}
