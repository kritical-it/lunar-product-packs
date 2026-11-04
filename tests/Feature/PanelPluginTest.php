<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Gongarce\ProductPacks\Filament\Resources\PackResource;
use Gongarce\ProductPacks\ProductPacksPlugin;

it('registers the pack resource in the lunar panel', function () {
    expect(Filament::getPanel('lunar')->getResources())->toContain(PackResource::class);
});

it('registers nothing when disabled', function () {
    config(['lunar.product-packs.enabled' => false]);
    $panel = Panel::make()->id('other');

    ProductPacksPlugin::make()->register($panel);

    expect($panel->getResources())->not->toContain(PackResource::class);
});
