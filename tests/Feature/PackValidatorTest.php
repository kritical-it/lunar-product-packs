<?php

use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\PackValidator;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;
use Lunar\Models\ProductOption;

function errorsOf(Pack $pack): array
{
    return app(PackValidator::class)->errors($pack->fresh());
}

it('accepts the cups and lids pack', function () {
    ['pack' => $pack] = cupsAndLids();
    $napkins = Catalog::product(['Color' => ['White', 'Black']]);
    $pack->items()->create(['product_id' => $napkins->id, 'position' => 3]);

    expect(errorsOf($pack))->toBe([]);
});

it('needs two products', function () {
    $pack = Pack::create(['status' => PackStatus::Published]);
    $pack->items()->create(['product_id' => Catalog::product()->id]);

    expect(errorsOf($pack))->toContain(__('product-packs::validation.fewer_than_two_items'));
});

it('rejects a rule on an option the product does not have', function () {
    ['pack' => $pack, 'lidItem' => $lidItem] = cupsAndLids();
    $other = ProductOption::factory()->create();
    $lidItem->options()->create(['product_option_id' => $other->id, 'mode' => OptionMode::Fixed, 'product_option_value_id' => null]);

    expect(errorsOf($pack))->toContain(__('product-packs::validation.option_not_on_product'));
});

it('rejects a fixed value of another option', function () {
    $mugs = Catalog::product(['Size' => ['S', 'L'], 'Color' => ['Red', 'Blue']]);
    $pack = Pack::create(['status' => PackStatus::Published]);
    $item = $pack->items()->create(['product_id' => $mugs->id]);
    $pack->items()->create(['product_id' => Catalog::product()->id, 'position' => 2]);
    $item->options()->create(['product_option_id' => Catalog::option($mugs, 'Size')->id, 'mode' => OptionMode::Fixed, 'product_option_value_id' => Catalog::value($mugs, 'Color', 'Red')->id]);

    expect(errorsOf($pack))->toContain(__('product-packs::validation.fixed_value_of_other_option'));
});

it('rejects a link with one column', function () {
    ['pack' => $pack, 'lidItem' => $lidItem] = cupsAndLids();
    $lidItem->options()->first()->delete();

    expect(errorsOf($pack))->toContain(__('product-packs::validation.link_with_one_column'));
});

it('rejects two columns of the same product in a link', function () {
    $mugs = Catalog::product(['Size' => ['S', 'L'], 'Color' => ['Red', 'Blue']]);
    $pack = Pack::create(['status' => PackStatus::Published]);
    $item = $pack->items()->create(['product_id' => $mugs->id]);
    $pack->items()->create(['product_id' => Catalog::product()->id, 'position' => 2]);
    $link = $pack->links()->create();
    foreach (['Size', 'Color'] as $name) {
        $item->options()->create(['product_option_id' => Catalog::option($mugs, $name)->id, 'mode' => OptionMode::Linked, 'pack_link_id' => $link->id]);
    }

    expect(errorsOf($pack))->toContain(__('product-packs::validation.link_columns_of_same_item'));
});

it('rejects a row without a value for every column', function () {
    ['pack' => $pack, 'link' => $link] = cupsAndLids();
    $link->rows()->first()->values()->first()->delete();

    expect(errorsOf($pack))->toContain(__('product-packs::validation.incomplete_row'));
});

it('rejects a row value of another option', function () {
    ['pack' => $pack, 'link' => $link, 'cups' => $cups] = cupsAndLids();
    $alien = Catalog::product(['Unidades' => ['100']]);
    $link->rows()->first()->values()->first()->update(['product_option_value_id' => Catalog::value($alien, 'Unidades', '100')->id]);

    expect(errorsOf($pack))->toContain(__('product-packs::validation.row_value_of_other_option'));
});

it('rejects two equal rows', function () {
    ['pack' => $pack, 'link' => $link] = cupsAndLids();
    $first = $link->rows()->first();
    $copy = $link->rows()->create(['position' => 3]);
    foreach ($first->values as $value) {
        $copy->values()->create($value->only(['pack_item_option_id', 'product_option_value_id']));
    }

    expect(errorsOf($pack))->toContain(__('product-packs::validation.duplicate_row'));
});
