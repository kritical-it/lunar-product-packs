<?php

namespace Gongarce\ProductPacks\Actions;

use Gongarce\ProductPacks\Models\Pack;
use InvalidArgumentException;
use Lunar\Models\Cart;
use Lunar\Models\ProductVariant;

/**
 * Add the chosen lines of a pack to a cart, all or none: Lunar adds them in
 * one transaction (Cart::addLines), so a line that fails its validation
 * (stock, quantity) adds nothing. Each line keeps the pack in its meta.
 */
class AddPackToCart
{
    /**
     * @param  list<array{variantId: int, quantity: int}>  $lines
     * @param  array<string, mixed>  $meta  more meta for every line
     *
     * @throws InvalidArgumentException a variant of a product outside the pack
     * @throws \Lunar\Exceptions\Carts\CartException a line Lunar does not accept
     */
    public function execute(Cart $cart, Pack $pack, array $lines, array $meta = []): Cart
    {
        $productIds = $pack->items()->pluck('product_id')->all();
        $variants = ProductVariant::modelClass()::query()
            ->whereIn('id', array_column($lines, 'variantId'))
            ->get()
            ->keyBy('id');

        $purchasables = [];
        foreach ($lines as $line) {
            $variant = $variants->get($line['variantId']);

            if (! $variant || ! in_array($variant->product_id, $productIds, true)) {
                throw new InvalidArgumentException("Variant {$line['variantId']} is not of a product of pack {$pack->id}.");
            }

            $purchasables[] = [
                'purchasable' => $variant,
                'quantity' => $line['quantity'],
                'meta' => ['pack' => $pack->id, ...$meta],
            ];
        }

        return $cart->addLines($purchasables);
    }
}
