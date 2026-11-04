<?php

use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Gongarce\ProductPacks\Models\PackItemOption;
use Gongarce\ProductPacks\Models\PackLink;
use Gongarce\ProductPacks\Models\PackLinkRow;
use Gongarce\ProductPacks\Models\PackLinkRowValue;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;
use Illuminate\Database\QueryException;

/**
 * Cups and lids linked by units, 100 ↔ 100 and 1000 ↔ 1000.
 */
function cupsAndLids(): array
{
    $cups = Catalog::product(['Unidades' => ['100', '1000']]);
    $lids = Catalog::product(['Unidades' => ['100', '1000']]);

    $pack = Pack::create(['title' => ['en' => 'Cups with lids'], 'status' => PackStatus::Published]);
    $cupItem = $pack->items()->create(['product_id' => $cups->id, 'position' => 1]);
    $lidItem = $pack->items()->create(['product_id' => $lids->id, 'position' => 2]);
    $link = $pack->links()->create(['position' => 1]);
    $cupUnits = $cupItem->options()->create(['product_option_id' => Catalog::option($cups, 'Unidades')->id, 'mode' => OptionMode::Linked, 'pack_link_id' => $link->id]);
    $lidUnits = $lidItem->options()->create(['product_option_id' => Catalog::option($lids, 'Unidades')->id, 'mode' => OptionMode::Linked, 'pack_link_id' => $link->id]);

    foreach (['100', '1000'] as $i => $units) {
        $row = $link->rows()->create(['position' => $i + 1]);
        $row->values()->create(['pack_item_option_id' => $cupUnits->id, 'product_option_value_id' => Catalog::value($cups, 'Unidades', $units)->id]);
        $row->values()->create(['pack_item_option_id' => $lidUnits->id, 'product_option_value_id' => Catalog::value($lids, 'Unidades', $units)->id]);
    }

    return compact('cups', 'lids', 'pack', 'cupItem', 'lidItem', 'link');
}

it('orders the items of a pack by position', function () {
    $pack = Pack::create(['status' => PackStatus::Draft]);
    $second = $pack->items()->create(['product_id' => Catalog::product()->id, 'position' => 2]);
    $first = $pack->items()->create(['product_id' => Catalog::product()->id, 'position' => 1]);

    expect($pack->items->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($first->quantity)->toBe(1)
        ->and($first->show_on_product)->toBeTrue()
        ->and($pack->status)->toBe(PackStatus::Draft);
});

it('deletes items, options, links, rows and values with the pack', function () {
    ['pack' => $pack] = cupsAndLids();

    $pack->delete();

    foreach ([Pack::class, PackItem::class, PackItemOption::class, PackLink::class, PackLinkRow::class, PackLinkRowValue::class] as $model) {
        expect($model::count())->toBe(0);
    }
});

it('does not allow the same product twice in a pack', function () {
    $pack = Pack::create(['status' => PackStatus::Published]);
    $product = Catalog::product();
    $pack->items()->create(['product_id' => $product->id, 'position' => 1]);

    $pack->items()->create(['product_id' => $product->id, 'position' => 2]);
})->throws(QueryException::class);

it('drops the fixed values and row values of a deleted option value', function () {
    ['cups' => $cups, 'cupItem' => $cupItem] = cupsAndLids();
    $hundred = Catalog::value($cups, 'Unidades', '100');

    $hundred->variants()->detach();
    $hundred->delete();

    expect(PackLinkRowValue::where('product_option_value_id', $hundred->id)->count())->toBe(0)
        ->and(PackLinkRowValue::count())->toBe(3)
        ->and($cupItem->options()->count())->toBe(1);
});

it('relates a product to the items it is in', function () {
    ['cups' => $cups, 'cupItem' => $cupItem] = cupsAndLids();

    expect($cups->packItems->pluck('id')->all())->toBe([$cupItem->id])
        ->and($cupItem->product->is($cups))->toBeTrue()
        ->and($cupItem->pack->title)->not->toBeNull();
});
