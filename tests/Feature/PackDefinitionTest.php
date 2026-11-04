<?php

use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Models\PackItemOption;
use Gongarce\ProductPacks\PackDefinition;
use Gongarce\ProductPacks\ProductPacks;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;
use Lunar\Models\Product;

function definitionFor(Product $current): array
{
    $pack = app(ProductPacks::class)->forProduct($current)->first();

    return app(PackDefinition::class)->make($pack, $current);
}

it('puts the current product first', function () {
    ['cups' => $cups, 'lids' => $lids, 'pack' => $pack] = cupsAndLids();

    $definition = definitionFor($lids);

    expect($definition['id'])->toBe($pack->id)
        ->and($definition['title'])->toBe('Cups with lids')
        ->and(array_column($definition['items'], 'productId'))->toBe([$lids->id, $cups->id])
        ->and(array_column($definition['items'], 'current'))->toBe([true, false]);
});

it('gives a null title to a pack without one', function () {
    ['cups' => $cups, 'pack' => $pack] = cupsAndLids();
    $pack->update(['title' => null]);

    expect(definitionFor($cups)['title'])->toBeNull();
});

it('lists fixed and linked options and leaves free ones out', function () {
    ['cups' => $cups, 'pack' => $pack, 'cupItem' => $cupItem, 'link' => $link] = cupsAndLids();
    $mugs = Catalog::product(['Size' => ['S', 'L'], 'Color' => ['Red', 'Blue']]);
    $mugItem = $pack->items()->create(['product_id' => $mugs->id, 'position' => 3, 'quantity' => 10]);
    $mugItem->options()->create(['product_option_id' => Catalog::option($mugs, 'Size')->id, 'mode' => OptionMode::Fixed, 'product_option_value_id' => Catalog::value($mugs, 'Size', 'L')->id]);

    $items = definitionFor($cups)['items'];

    expect($items[0])->toBe([
        'id' => $cupItem->id, 'productId' => $cups->id, 'quantity' => 1, 'current' => true,
        'options' => [['optionId' => Catalog::option($cups, 'Unidades')->id, 'mode' => 'linked', 'linkId' => $link->id]],
    ])->and($items[2])->toBe([
        'id' => $mugItem->id, 'productId' => $mugs->id, 'quantity' => 10, 'current' => false,
        'options' => [['optionId' => Catalog::option($mugs, 'Size')->id, 'mode' => 'fixed', 'valueId' => Catalog::value($mugs, 'Size', 'L')->id]],
    ]);
});

it('gives each link its columns and rows in order', function () {
    ['cups' => $cups, 'lids' => $lids, 'cupItem' => $cupItem, 'lidItem' => $lidItem, 'link' => $link] = cupsAndLids();

    expect(definitionFor($lids)['links'])->toBe([[
        'id' => $link->id,
        'columns' => [
            ['itemId' => $lidItem->id, 'optionId' => Catalog::option($lids, 'Unidades')->id],
            ['itemId' => $cupItem->id, 'optionId' => Catalog::option($cups, 'Unidades')->id],
        ],
        'rows' => [
            [Catalog::value($lids, 'Unidades', '100')->id, Catalog::value($cups, 'Unidades', '100')->id],
            [Catalog::value($lids, 'Unidades', '1000')->id, Catalog::value($cups, 'Unidades', '1000')->id],
        ],
    ]]);
});

it('drops the columns of a product that is left out', function () {
    ['cups' => $cups, 'lids' => $lids, 'pack' => $pack, 'cupItem' => $cupItem, 'link' => $link] = cupsAndLids();
    $napkins = Catalog::product();
    $pack->items()->create(['product_id' => $napkins->id, 'position' => 3]);
    $lids->update(['status' => 'draft']);

    $links = definitionFor($cups)['links'];

    expect($links[0]['columns'])->toBe([['itemId' => $cupItem->id, 'optionId' => Catalog::option($cups, 'Unidades')->id]])
        ->and($links[0]['rows'])->toBe([[Catalog::value($cups, 'Unidades', '100')->id], [Catalog::value($cups, 'Unidades', '1000')->id]]);
});

it('ignores rules on options the product no longer has', function () {
    ['cups' => $cups, 'lids' => $lids, 'cupItem' => $cupItem, 'lidItem' => $lidItem] = cupsAndLids();
    $lids->productOptions()->detach();

    $definition = definitionFor($cups);

    expect($definition['items'][1]['options'])->toBe([])
        ->and($definition['links'][0]['columns'])->toBe([['itemId' => $cupItem->id, 'optionId' => Catalog::option($cups, 'Unidades')->id]]);
});

it('drops rows left incomplete by a deleted option value', function () {
    ['cups' => $cups, 'lids' => $lids] = cupsAndLids();
    $hundred = Catalog::value($lids, 'Unidades', '100');
    $hundred->variants()->detach();
    $hundred->delete();

    expect(definitionFor($cups)['links'][0]['rows'])->toBe([
        [Catalog::value($cups, 'Unidades', '1000')->id, Catalog::value($lids, 'Unidades', '1000')->id],
    ]);
});

it('leaves out a pack whose fixed value was deleted, instead of freeing the option', function () {
    ['cups' => $cups, 'pack' => $pack, 'lidItem' => $lidItem] = cupsAndLids();
    $mugs = Catalog::product(['Size' => ['S', 'L']]);
    $mugItem = $pack->items()->create(['product_id' => $mugs->id, 'position' => 3]);
    $large = Catalog::value($mugs, 'Size', 'L');
    $mugItem->options()->create(['product_option_id' => Catalog::option($mugs, 'Size')->id, 'mode' => OptionMode::Fixed, 'product_option_value_id' => $large->id]);

    $large->variants()->detach();
    $large->delete();

    expect($mugItem->options()->count())->toBe(1)
        ->and(app(ProductPacks::class)->forProduct($cups))->toBeEmpty();
});

it('drops a linked rule whose link is not of its pack', function () {
    ['cups' => $cups, 'cupItem' => $cupItem] = cupsAndLids();
    $cupItem->options()->first()->update(['pack_link_id' => cupsAndLids()['link']->id]);

    expect(definitionFor($cups)['items'][0]['options'])->toBe([]);
});
