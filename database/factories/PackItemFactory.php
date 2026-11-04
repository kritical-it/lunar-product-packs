<?php

namespace Gongarce\ProductPacks\Database\Factories;

use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Lunar\Models\Product;

class PackItemFactory extends Factory
{
    protected $model = PackItem::class;

    public function definition(): array
    {
        return [
            'pack_id' => Pack::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
            'show_on_product' => true,
            'position' => 1,
        ];
    }
}
