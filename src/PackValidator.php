<?php

namespace Gongarce\ProductPacks;

use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItemOption;
use Gongarce\ProductPacks\Models\PackLink;
use Gongarce\ProductPacks\Models\PackLinkRow;

/**
 * What makes a pack inconsistent: its products, the rules of their options
 * and the pairs of its links. The admin shows the errors before saving.
 */
class PackValidator
{
    /**
     * @return list<string> translated messages, each once; empty when valid
     */
    public function errors(Pack $pack): array
    {
        $pack->loadMissing([
            'items.product.productOptions',
            'items.options.value',
            'links.columns',
            'links.rows.values.value',
        ]);

        $errors = [];

        if ($pack->items->count() < 2) {
            $errors[] = 'fewer_than_two_items';
        }

        foreach ($pack->items as $item) {
            $optionIds = $item->product?->productOptions->modelKeys() ?? [];

            foreach ($item->options as $rule) {
                if (! in_array($rule->product_option_id, $optionIds, true)) {
                    $errors[] = 'option_not_on_product';
                }

                if ($rule->mode === OptionMode::Fixed && $rule->value?->product_option_id !== $rule->product_option_id) {
                    $errors[] = 'fixed_value_of_other_option';
                }
            }
        }

        foreach ($pack->links as $link) {
            array_push($errors, ...$this->linkErrors($link));
        }

        return array_values(array_map(
            fn (string $key) => __("product-packs::validation.{$key}"),
            array_unique($errors),
        ));
    }

    /**
     * @return list<string> keys
     */
    private function linkErrors(PackLink $link): array
    {
        $errors = [];
        $columns = $link->columns;

        if ($columns->count() < 2) {
            $errors[] = 'link_with_one_column';
        }

        if ($columns->pluck('pack_item_id')->duplicates()->isNotEmpty()) {
            $errors[] = 'link_columns_of_same_item';
        }

        $columnOptions = $columns->mapWithKeys(fn (PackItemOption $column) => [$column->id => $column->product_option_id]);
        $seen = [];

        foreach ($link->rows as $row) {
            /** @var PackLinkRow $row */
            $values = $row->values->keyBy('pack_item_option_id');

            if ($columnOptions->keys()->diff($values->keys())->isNotEmpty()) {
                $errors[] = 'incomplete_row';
            }

            foreach ($values as $columnId => $value) {
                if ($value->value?->product_option_id !== $columnOptions->get($columnId)) {
                    $errors[] = 'row_value_of_other_option';
                }
            }

            $signature = $values->sortKeys()->map->product_option_value_id->toJson();
            if (isset($seen[$signature])) {
                $errors[] = 'duplicate_row';
            }
            $seen[$signature] = true;
        }

        return $errors;
    }
}
