<?php

use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Models\Pack;
use KriticalIt\ProductPacks\Tests\Fixtures\Catalog;
use Lunar\Models\ProductAssociation;

function crossSell($parent, $target): void
{
    ProductAssociation::create(['product_parent_id' => $parent->id, 'product_target_id' => $target->id, 'type' => ProductAssociation::CROSS_SELL]);
}

it('turns A → B into a pack shown on A', function () {
    [$a, $b] = [Catalog::product(), Catalog::product()];
    crossSell($a, $b);

    $this->artisan('lunar:packs:import-cross-sell')->assertSuccessful();

    $pack = Pack::with('items')->sole();
    expect($pack->status)->toBe(PackStatus::Published)
        ->and($pack->title)->toBeNull()
        ->and($pack->items->map->only(['product_id', 'quantity', 'show_on_product', 'position'])->all())->toBe([
            ['product_id' => $a->id, 'quantity' => 1, 'show_on_product' => true, 'position' => 1],
            ['product_id' => $b->id, 'quantity' => 1, 'show_on_product' => false, 'position' => 2],
        ])
        ->and($pack->items->flatMap->options)->toBeEmpty();
});

it('turns A → B and B → A into one pack shown on both', function () {
    [$a, $b] = [Catalog::product(), Catalog::product()];
    crossSell($a, $b);
    crossSell($b, $a);

    $this->artisan('lunar:packs:import-cross-sell')->assertSuccessful();

    expect(Pack::count())->toBe(1)
        ->and(Pack::sole()->items->pluck('show_on_product')->all())->toBe([true, true]);
});

it('does not duplicate when run twice', function () {
    [$a, $b, $c] = [Catalog::product(), Catalog::product(), Catalog::product()];
    crossSell($a, $b);
    crossSell($a, $c);

    $this->artisan('lunar:packs:import-cross-sell')->assertSuccessful();
    $this->artisan('lunar:packs:import-cross-sell')->assertSuccessful();

    expect(Pack::count())->toBe(2);
});

it('only reports with --dry-run', function () {
    [$a, $b] = [Catalog::product(), Catalog::product()];
    crossSell($a, $b);

    $this->artisan('lunar:packs:import-cross-sell', ['--dry-run' => true])
        ->expectsOutputToContain('1')
        ->assertSuccessful();

    expect(Pack::count())->toBe(0);
});

it('keeps the cross-sell associations', function () {
    [$a, $b] = [Catalog::product(), Catalog::product()];
    crossSell($a, $b);

    $this->artisan('lunar:packs:import-cross-sell')->assertSuccessful();

    expect(ProductAssociation::count())->toBe(1);
});

it('skips a product associated with itself', function () {
    [$a, $b] = [Catalog::product(), Catalog::product()];
    crossSell($a, $a);
    crossSell($a, $b);

    $this->artisan('lunar:packs:import-cross-sell')->assertSuccessful();

    expect(Pack::count())->toBe(1);
});
