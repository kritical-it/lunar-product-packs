<?php

use Filament\Facades\Filament;
use Filament\Panel;
use KriticalIt\ProductPacks\Filament\Resources\PackResource;
use KriticalIt\ProductPacks\ProductPacksPlugin;

it('registers the pack resource in the lunar panel', function () {
    expect(Filament::getPanel('lunar')->getResources())->toContain(PackResource::class);
});

it('registers nothing when disabled', function () {
    config(['lunar.product-packs.enabled' => false]);
    $panel = Panel::make()->id('other');

    ProductPacksPlugin::make()->register($panel);

    expect($panel->getResources())->not->toContain(PackResource::class);
});
