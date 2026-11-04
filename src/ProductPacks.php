<?php

namespace Gongarce\ProductPacks;

use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lunar\Models\Product;

/**
 * The packs a product page shows.
 */
class ProductPacks
{
    /**
     * The relations PackDefinition reads, loaded with the packs.
     */
    public const RELATIONS = [
        'items.product.productOptions',
        'items.options',
        'links.columns',
        'links.rows.values',
    ];

    /**
     * Published packs where the product shows, each with only its products
     * that are published and not deleted, and only those left with two or
     * more of them. By id.
     *
     * @return Collection<int, Pack>
     */
    public function forProduct(Product $product): Collection
    {
        return Pack::modelClass()::query()
            ->where('status', PackStatus::Published)
            ->whereHas('items', fn (Builder $query) => $query
                ->where('product_id', $product->getKey())
                ->where('show_on_product', true))
            ->with(self::RELATIONS)
            ->orderBy('id')
            ->get()
            ->each(fn (Pack $pack) => $pack->setRelation('items', self::visibleItems($pack)))
            ->filter(fn (Pack $pack) => $pack->items->count() >= 2
                && $pack->items->contains('product_id', $product->getKey()))
            ->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PackItem>
     */
    public static function visibleItems(Pack $pack): \Illuminate\Database\Eloquent\Collection
    {
        // Soft deleted products are not loaded by the relation at all.
        return $pack->items
            ->filter(fn (PackItem $item) => $item->product?->status === 'published')
            ->values();
    }
}
