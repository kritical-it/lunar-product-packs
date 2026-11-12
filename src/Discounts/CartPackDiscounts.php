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
 * Scoped: the packs with a discount load once per request (every cart
 * calculation asks for them), and a PackChanged forgets them.
 */
class CartPackDiscounts
{
    /** @var Collection<int, Pack>|null */
    private ?Collection $packs = null;

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

        if ($lines->isEmpty() || $this->packs()->isEmpty()) {
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

        return $this->matcher->match($matchLines, $this->packs());
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
        $this->packs = null;
    }

    /**
     * @return Collection<int, Pack>
     */
    private function packs(): Collection
    {
        return $this->packs ??= $this->productPacks->discounted();
    }
}
