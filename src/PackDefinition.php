<?php

namespace Gongarce\ProductPacks;

use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Gongarce\ProductPacks\Models\PackItemOption;
use Gongarce\ProductPacks\Models\PackLink;
use Gongarce\ProductPacks\Models\PackLinkRow;
use Illuminate\Support\Collection;
use Lunar\Models\Product;

/**
 * A pack as any storefront receives it, from the page of one of its
 * products: that product first, the rules of the options of each product
 * (free options have none) and the pairs of each link, a value per column.
 *
 * Only what the storefront can use: products left out (unpublished,
 * deleted), rules on options a product no longer has and pairs left without
 * a value for some column are dropped.
 */
class PackDefinition
{
    /**
     * @return array{id: int, title: ?string, items: list<array>, links: list<array>}
     */
    public function make(Pack $pack, Product $current): array
    {
        $pack->loadMissing(ProductPacks::RELATIONS);

        $items = ProductPacks::visibleItems($pack)
            ->sortBy(fn (PackItem $item) => $item->product_id === $current->getKey() ? 0 : 1)
            ->values();

        $rules = $items->flatMap(fn (PackItem $item) => $item->options
            ->filter(fn (PackItemOption $rule) => $item->product->productOptions->contains($rule->product_option_id)))
            ->keyBy('id');

        return [
            'id' => $pack->id,
            'title' => $pack->title ? $pack->translate('title') : null,
            'items' => $items->map(fn (PackItem $item) => [
                'id' => $item->id,
                'productId' => $item->product_id,
                'quantity' => $item->quantity,
                'current' => $item->product_id === $current->getKey(),
                'options' => $rules->where('pack_item_id', $item->id)->values()->map(fn (PackItemOption $rule) => [
                    'optionId' => $rule->product_option_id,
                    'mode' => $rule->mode->value,
                    ...($rule->mode === OptionMode::Fixed ? ['valueId' => $rule->product_option_value_id] : ['linkId' => $rule->pack_link_id]),
                ])->all(),
            ])->all(),
            'links' => $pack->links
                ->map(fn (PackLink $link) => $this->link($link, $items, $rules))
                ->filter(fn (array $link) => $link['columns'] !== [])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, PackItem>  $items  in the order of the definition
     * @param  Collection<int, PackItemOption>  $rules  the rules kept, by id
     */
    private function link(PackLink $link, Collection $items, Collection $rules): array
    {
        $order = $items->pluck('id')->flip();

        $columns = $link->columns
            ->filter(fn (PackItemOption $column) => $rules->has($column->id))
            ->sortBy(fn (PackItemOption $column) => $order[$column->pack_item_id])
            ->values();

        $rows = $link->rows
            ->map(function (PackLinkRow $row) use ($columns) {
                $values = $row->values->pluck('product_option_value_id', 'pack_item_option_id');

                return $columns->map(fn (PackItemOption $column) => $values->get($column->id))->all();
            })
            ->reject(fn (array $values) => in_array(null, $values, true))
            ->values()
            ->all();

        return [
            'id' => $link->id,
            'columns' => $columns->map(fn (PackItemOption $column) => [
                'itemId' => $column->pack_item_id,
                'optionId' => $column->product_option_id,
            ])->all(),
            'rows' => $rows,
        ];
    }
}
