<?php

namespace Gongarce\ProductPacks\Tests\Fixtures;

use Illuminate\Support\Arr;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;

/**
 * Published products with their options and one variant (with a price) for
 * each combination of values.
 */
class Catalog
{
    /**
     * @param  array<string, list<string>>  $options  ['Unidades' => ['100', '1000']]
     */
    public static function product(array $options = [], array $attributes = []): Product
    {
        $product = Product::factory()->create(['status' => 'published', ...$attributes]);

        $values = [];
        $position = 1;
        foreach ($options as $name => $names) {
            $option = ProductOption::factory()->create(['name' => ['en' => $name], 'handle' => null]);
            $product->productOptions()->attach($option->id, ['position' => $position++]);
            $values[] = array_map(fn (string $value, int $i) => ProductOptionValue::create([
                'product_option_id' => $option->id,
                'name' => ['en' => $value],
                'position' => $i + 1,
            ]), $names, array_keys($names));
        }

        foreach ($values === [] ? [[]] : Arr::crossJoin(...$values) as $combination) {
            $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
            Price::factory()->create([
                'priceable_type' => $variant->getMorphClass(),
                'priceable_id' => $variant->id,
                'currency_id' => Currency::getDefault()->id,
                'price' => 1000,
                'min_quantity' => 1,
            ]);
            $variant->values()->attach(array_map(fn (ProductOptionValue $value) => $value->id, $combination));
        }

        return $product->fresh();
    }

    public static function option(Product $product, string $name): ProductOption
    {
        return $product->productOptions->first(fn (ProductOption $option) => $option->translate('name') === $name);
    }

    public static function value(Product $product, string $option, string $value): ProductOptionValue
    {
        return self::option($product, $option)->values->first(fn (ProductOptionValue $candidate) => $candidate->translate('name') === $value);
    }
}
