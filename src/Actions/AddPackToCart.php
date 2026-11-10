<?php

namespace KriticalIt\ProductPacks\Actions;

use Illuminate\Support\Facades\DB;
use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Exceptions\PackLineRejected;
use KriticalIt\ProductPacks\Exceptions\PackNotAvailable;
use KriticalIt\ProductPacks\Exceptions\PackProductNotAvailable;
use KriticalIt\ProductPacks\Models\Pack;
use KriticalIt\ProductPacks\ProductPacks;
use Lunar\Exceptions\Carts\CartException;
use Lunar\Models\Cart;
use Lunar\Models\ProductVariant;

/**
 * Add the chosen lines of a pack to a cart, all or none: in one transaction,
 * as Cart::addLines, so a line that fails its validation (stock, quantity)
 * adds nothing; one by one, to tell which line failed (PackLineRejected).
 *
 * The lines carry no mark of the pack: a variant added from a pack goes to
 * the same line as when added from its page. A pack discount, when there is
 * one, will look at what the cart holds.
 */
class AddPackToCart
{
    /**
     * @param  list<array{variantId: int, quantity: int}>  $lines
     * @param  array<string, mixed>  $meta  more meta for every line
     *
     * @throws PackNotAvailable a pack not published
     * @throws PackProductNotAvailable a variant of a product the pack does not show
     * @throws PackLineRejected a line Lunar does not accept
     */
    public function execute(Cart $cart, Pack $pack, array $lines, array $meta = []): Cart
    {
        if ($pack->status !== PackStatus::Published) {
            throw new PackNotAvailable("Pack {$pack->id} is not published.");
        }

        // Only the products the pack shows: published and not deleted.
        $productIds = ProductPacks::visibleItems($pack->loadMissing('items.product'))->pluck('product_id')->all();
        $variants = ProductVariant::modelClass()::query()
            ->whereIn('id', array_column($lines, 'variantId'))
            ->get()
            ->keyBy('id');

        $purchasables = [];
        foreach ($lines as $line) {
            $variant = $variants->get($line['variantId']);

            if (! $variant || ! in_array($variant->product_id, $productIds, true)) {
                throw new PackProductNotAvailable($line['variantId'], $pack->id);
            }

            $purchasables[] = [
                'purchasable' => $variant,
                'quantity' => $line['quantity'],
                'meta' => $meta,
            ];
        }

        DB::transaction(function () use ($cart, $purchasables) {
            foreach ($purchasables as $line) {
                try {
                    $cart->add($line['purchasable'], $line['quantity'], $line['meta'], refresh: false);
                } catch (CartException $e) {
                    throw new PackLineRejected($e, $line['purchasable'], $line['quantity']);
                }
            }
        });

        return $cart->refresh()->recalculate();
    }
}
