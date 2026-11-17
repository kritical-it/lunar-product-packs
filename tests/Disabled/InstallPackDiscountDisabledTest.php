<?php

use KriticalIt\ProductPacks\Discounts\PackDiscount;
use Lunar\Models\Discount;

it('installs the discount with the plugin turned off, so the migration of a shop never fails', function () {
    $this->artisan('product-packs:install-discount')->assertSuccessful();

    expect(Discount::where('type', PackDiscount::class)->count())->toBe(1);
});
