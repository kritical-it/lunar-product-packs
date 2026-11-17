<?php

namespace KriticalIt\ProductPacks\Discounts;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use KriticalIt\ProductPacks\Models\Pack;
use KriticalIt\ProductPacks\ProductPacks;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Contracts\CartLine;
use Lunar\Models\Contracts\ProductVariant;

/**
 * The complete packs of a cart, and what each of its lines takes off.
 *
 * Scoped: the packs with a discount and a product of the cart load once
 * per request and set of products (every cart calculation asks for them),
 * and a PackChanged forgets them.
 */
class CartPackDiscounts
{
    /** @var array<string, Collection<int, Pack>> by the sorted ids of the products of the cart */
    private array $packs = [];

    public function __construct(
        private ProductPacks $productPacks,
        private PackMatcher $matcher,
    ) {}

    /**
     * @return list<PackMatch>
     */
    public function for(Cart $cart): array
    {
        /** @var \Lunar\Models\Cart $cart */
        $lines = $cart->lines->filter(fn (CartLine $line) => $line->purchasable instanceof ProductVariant);

        $packs = $this->packs($lines->map(fn (CartLine $line) => (int) $line->purchasable->product_id)->unique()->sort()->values()->all());

        if ($packs->isEmpty()) {
            return [];
        }

        (new EloquentCollection($lines->pluck('purchasable')))->loadMissing('values');

        $matchLines = $lines->map(fn (CartLine $line) => new MatchLine(
            id: $line->id,
            productId: $line->purchasable->product_id,
            variantId: $line->purchasable->id,
            valueIds: $line->purchasable->values->pluck('id')->map(fn ($id) => (int) $id)->all(),
            quantity: $line->quantity,
        ))->values();

        return $this->matcher->match($matchLines, $packs);
    }

    /**
     * The packs of the cart that Lunar did discount, with what each line took
     * off: none when the record of the discount is off, expired or not for
     * this channel or customer group, nor a pack whose lines a coupon left at
     * 0. Calculates the cart if it is not yet.
     *
     * @return list<PackDiscountBreakdown>
     */
    public function applied(Cart $cart): array
    {
        /** @var \Lunar\Models\Cart $cart */
        $cart->calculate();

        return ($cart->discountBreakdown ?? collect())
            ->filter(fn ($breakdown) => $breakdown instanceof PackDiscountBreakdown)
            ->values()
            ->all();
    }

    /**
     * What a line takes off for its units in packs, in cents without tax:
     * the percentage of the unit price times those units.
     */
    public function amount(CartLine $line, int $units, float $percentage): int
    {
        /** @var \Lunar\Models\CartLine $line */
        return (int) round($line->subTotal->value * $units / $line->quantity * $percentage / 100);
    }

    public function forget(): void
    {
        $this->packs = [];
    }

    /**
     * @param  list<int>  $productIds  sorted
     * @return Collection<int, Pack>
     */
    private function packs(array $productIds): Collection
    {
        return $this->packs[implode(',', $productIds)] ??= $this->productPacks->discounted($productIds);
    }
}
