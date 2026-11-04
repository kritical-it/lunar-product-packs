<?php

use Filament\Facades\Filament;
use Gongarce\ProductPacks\Filament\Resources\PackResource;
use Gongarce\ProductPacks\Filament\Resources\ProductResource\Pages\ManageProductPacks;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    $this->asStaff();
});

it('lists only the packs of the product', function () {
    $cups = Catalog::product();
    $first = packWith([$cups, Catalog::product()]);
    $second = packWith([Catalog::product(), $cups]);
    $other = packWith([Catalog::product(), Catalog::product()]);

    Livewire::test(ManageProductPacks::class, ['record' => $cups->getRouteKey()])
        ->assertCanSeeTableRecords([
            $first->items()->where('product_id', $cups->id)->sole(),
            $second->items()->where('product_id', $cups->id)->sole(),
        ])
        ->assertCanNotSeeTableRecords($other->items);
});

it('links to create a pack with this product', function () {
    $cups = Catalog::product();

    Livewire::test(ManageProductPacks::class, ['record' => $cups->getRouteKey()])
        ->assertActionHasUrl('create_pack', PackResource::getUrl('create', ['product' => $cups->id]));
});

it('links each pack to its edit page', function () {
    $cups = Catalog::product();
    $pack = packWith([$cups, Catalog::product()]);

    Livewire::test(ManageProductPacks::class, ['record' => $cups->getRouteKey()])
        ->assertTableActionHasUrl('edit', PackResource::getUrl('edit', ['record' => $pack]), $pack->items()->where('product_id', $cups->id)->sole());
});

it('adds the tab to the product', function () {
    $cups = Catalog::product();

    expect(\Lunar\Admin\Filament\Resources\ProductResource::getPages())->toHaveKey('packs');
});
