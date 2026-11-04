<?php

use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\ProductPacks;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;

function packIdsFor($product): array
{
    return app(ProductPacks::class)->forProduct($product)->modelKeys();
}

it('gives the published packs a product shows', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids] = cupsAndLids();

    expect(packIdsFor($cups))->toBe([$pack->id])
        ->and(packIdsFor($lids))->toBe([$pack->id])
        ->and(packIdsFor(Catalog::product()))->toBe([]);
});

it('leaves out draft packs', function () {
    ['pack' => $pack, 'cups' => $cups] = cupsAndLids();
    $pack->update(['status' => PackStatus::Draft]);

    expect(packIdsFor($cups))->toBe([]);
});

it('leaves out packs not shown on this product', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids, 'lidItem' => $lidItem] = cupsAndLids();
    $lidItem->update(['show_on_product' => false]);

    expect(packIdsFor($lids))->toBe([])
        ->and(packIdsFor($cups))->toBe([$pack->id]);
});

it('leaves out unpublished and soft deleted products of a pack', function () {
    ['pack' => $pack, 'cups' => $cups] = cupsAndLids();
    $napkins = Catalog::product();
    $towels = Catalog::product();
    $pack->items()->create(['product_id' => $napkins->id, 'position' => 3]);
    $pack->items()->create(['product_id' => $towels->id, 'position' => 4]);
    $napkins->update(['status' => 'draft']);
    $towels->delete();

    $packs = app(ProductPacks::class)->forProduct($cups);

    expect($packs->first()->items->pluck('product_id')->all())->not->toContain($napkins->id, $towels->id)
        ->and($packs->first()->items)->toHaveCount(2);
});

it('leaves out a pack left with one product', function () {
    ['cups' => $cups, 'lids' => $lids] = cupsAndLids();
    $lids->update(['status' => 'draft']);

    expect(packIdsFor($cups))->toBe([]);
});
