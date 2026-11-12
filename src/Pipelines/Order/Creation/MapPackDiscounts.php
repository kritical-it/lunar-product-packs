<?php

namespace KriticalIt\ProductPacks\Pipelines\Order\Creation;

use Closure;
use KriticalIt\ProductPacks\Discounts\CartPackDiscounts;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;
use Lunar\Models\Tag;

/**
 * Records on the order the packs that took a discount, and tags it PACK.
 *
 * It goes on the order meta, not on the lines: CreateOrderLines matches the
 * order lines to the cart lines by their meta. Add it after
 * MapDiscountBreakdown in config/lunar/orders.php. The other tags stay as
 * they are.
 */
class MapPackDiscounts
{
    public const TAG = 'PACK';

    public function __construct(private CartPackDiscounts $discounts) {}

    public function handle(OrderContract $order, Closure $next): mixed
    {
        /** @var Order $order */
        /** @var Cart $cart */
        // The pipes before refresh the order, and with it its cart: a fresh one,
        // whose lines have no totals until it is calculated.
        $cart = $order->cart->calculate();
        $packs = [];

        foreach ($this->discounts->for($cart) as $match) {
            $percentage = (float) $match->pack->discount_percentage;
            $lines = [];

            foreach ($match->units as $lineId => $units) {
                /** @var CartLine $line */
                $line = $cart->lines->firstWhere('id', $lineId);

                if (! $line || $units < 1) {
                    continue;
                }

                $lines[] = [
                    'purchasable_id' => $line->purchasable_id,
                    'quantity' => $units,
                    'discount' => $this->discounts->amount($line, $units, $percentage),
                ];
            }

            $packs[] = [
                'pack_id' => $match->pack->id,
                'title' => $match->pack->translate('title') ?: null,
                'percentage' => $percentage,
                'count' => $match->count,
                'lines' => $lines,
            ];
        }

        $meta = $order->meta;

        if ($packs) {
            $meta['pack_discounts'] = $packs;
            $order->meta = $meta;
            $order->save();
            $order->tags()->syncWithoutDetaching([Tag::firstOrCreate(['value' => self::TAG])->id]);
        } else {
            if (isset($meta['pack_discounts'])) {
                unset($meta['pack_discounts']);
                $order->meta = $meta;
                $order->save();
            }
            if ($tag = Tag::where('value', self::TAG)->first()) {
                $order->tags()->detach($tag->id);
            }
        }

        return $next($order);
    }
}
