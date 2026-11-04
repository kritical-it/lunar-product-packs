<?php

use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Events\PackChangeReason;
use Gongarce\ProductPacks\Filament\Resources\PackResource\Pages\CreatePack;
use Gongarce\ProductPacks\Filament\Resources\PackResource\Pages\EditPack;
use Gongarce\ProductPacks\Filament\Resources\PackResource\Pages\ListPacks;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    $this->asStaff();
});

it('creates a draft pack with its products', function () {
    $cups = Catalog::product();
    $lids = Catalog::product();
    fakePackEvents();

    Livewire::test(CreatePack::class)
        ->fillForm([
            'title' => ['en' => 'Cups with lids'],
            'status' => PackStatus::Draft->value,
            'items' => [
                'a' => ['product_id' => $cups->id, 'quantity' => 1, 'show_on_product' => true],
                'b' => ['product_id' => $lids->id, 'quantity' => 10, 'show_on_product' => false],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pack = Pack::sole();
    expect($pack->status)->toBe(PackStatus::Draft)
        ->and($pack->translate('title'))->toBe('Cups with lids')
        ->and($pack->items->map->only('product_id', 'quantity', 'show_on_product', 'position')->all())->toBe([
            ['product_id' => $cups->id, 'quantity' => 1, 'show_on_product' => true, 'position' => 1],
            ['product_id' => $lids->id, 'quantity' => 10, 'show_on_product' => false, 'position' => 2],
        ])
        ->and(packEvents())->toHaveCount(1);
});

it('puts the given product first when creating from a product', function () {
    $cups = Catalog::product();

    $items = Livewire::withQueryParams(['product' => $cups->id])
        ->test(CreatePack::class)
        ->get('data.items');

    expect(array_values($items)[0]['product_id'])->toBe($cups->id);
});

it('refuses to create a published pack with errors', function () {
    Livewire::test(CreatePack::class)
        ->fillForm([
            'status' => PackStatus::Published->value,
            'items' => ['a' => ['product_id' => Catalog::product()->id, 'quantity' => 1, 'show_on_product' => true]],
        ])
        ->call('create')
        ->assertHasFormErrors(['status']);

    expect(Pack::count())->toBe(0);
});

it('refuses to publish a pack with errors', function () {
    $pack = packWith([Catalog::product()]);

    Livewire::test(EditPack::class, ['record' => $pack->getRouteKey()])
        ->fillForm(['status' => PackStatus::Published->value])
        ->call('save')
        ->assertHasFormErrors(['status']);

    expect($pack->refresh()->status)->toBe(PackStatus::Draft)
        ->and($pack->items)->toHaveCount(1);
});

it('publishes a valid pack', function () {
    ['pack' => $pack] = cupsAndLids();
    $pack->update(['status' => PackStatus::Draft]);

    Livewire::test(EditPack::class, ['record' => $pack->getRouteKey()])
        ->fillForm(['status' => PackStatus::Published->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pack->refresh()->status)->toBe(PackStatus::Published);
});

it('removes a product and saves once', function () {
    $pack = packWith([Catalog::product(), Catalog::product(), $last = Catalog::product()]);
    fakePackEvents();

    $page = Livewire::test(EditPack::class, ['record' => $pack->getRouteKey()]);
    $items = $page->get('data.items');
    $lastKey = collect($items)->search(fn ($item) => $item['product_id'] === $last->id);
    unset($items[$lastKey]);

    $page->set('data.items', $items)->call('save')->assertHasNoFormErrors();

    expect($pack->items()->pluck('product_id')->all())->not->toContain($last->id)
        ->and($pack->items()->count())->toBe(2)
        ->and(packEvents())->toHaveCount(1);
});

it('lists packs and flags the ones with errors', function () {
    ['pack' => $good] = cupsAndLids();
    $bad = packWith([Catalog::product()]);

    Livewire::test(ListPacks::class)
        ->assertCanSeeTableRecords([$good, $bad])
        ->assertTableColumnStateSet('has_errors', false, $good)
        ->assertTableColumnStateSet('has_errors', true, $bad);
});

it('deletes a pack', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids] = cupsAndLids();
    fakePackEvents();

    Livewire::test(ListPacks::class)->callTableAction(DeleteAction::class, $pack);

    $this->assertModelMissing($pack);
    expect(packEvents(PackChangeReason::Deleted)->sole()->productIds)->toBe([$cups->id, $lids->id]);
});

it('tidies the links after removing a product', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids, 'cupItem' => $cupItem, 'lidItem' => $lidItem, 'link' => $link] = cupsAndLids();
    $pack->items()->create(['product_id' => Catalog::product()->id, 'position' => 3]);
    $cupRule = $cupItem->options()->sole();
    $row = $link->rows()->create(['position' => 3]);
    $row->values()->create(['pack_item_option_id' => $cupRule->id, 'product_option_value_id' => Catalog::value($cups, 'Unidades', '100')->id]);
    $row->values()->create(['pack_item_option_id' => $lidItem->options()->sole()->id, 'product_option_value_id' => Catalog::value($lids, 'Unidades', '1000')->id]);

    $page = Livewire::test(EditPack::class, ['record' => $pack->getRouteKey()]);
    $items = $page->get('data.items');
    unset($items[collect($items)->search(fn ($item) => $item['product_id'] === $lids->id)]);
    $page->set('data.items', $items)->call('save')->assertHasNoFormErrors();

    expect($link->rows()->count())->toBe(2);
});

it('creates a draft after a refused publish', function () {
    $page = Livewire::test(CreatePack::class)
        ->fillForm([
            'status' => PackStatus::Published->value,
            'items' => ['a' => ['product_id' => Catalog::product()->id, 'quantity' => 1, 'show_on_product' => true]],
        ])
        ->call('create')
        ->assertHasFormErrors(['status']);

    $page->fillForm(['status' => PackStatus::Draft->value])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Pack::count())->toBe(1);
});

it('warns on the edit page of a pack with errors', function () {
    $pack = packWith([Catalog::product()], PackStatus::Published);

    Livewire::test(EditPack::class, ['record' => $pack->getRouteKey()])
        ->assertSee(__('product-packs::validation.fewer_than_two_items'));
});

it('forbids staff without the permission to manage products', function () {
    $staff = \Lunar\Admin\Models\Staff::create([
        'first_name' => 'No', 'last_name' => 'Rights', 'email' => 'none@example.test',
        'password' => bcrypt('password'), 'admin' => false,
    ]);
    $this->actingAs($staff, 'staff');

    expect(\Gongarce\ProductPacks\Filament\Resources\PackResource::canViewAny())->toBeFalse();
});

/**
 * Queries to render the page, for $packs packs of cups and lids.
 */
function queriesToList(string $page, array $params, int $packs, ?callable $make = null): int
{
    foreach (range(1, $packs) as $i) {
        $make ? $make() : cupsAndLids();
    }

    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();
    Livewire::test($page, $params);
    $count = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::disableQueryLog();

    return $count;
}

it('lists packs with the same queries for one or ten', function () {
    $one = queriesToList(ListPacks::class, [], 1);
    $ten = queriesToList(ListPacks::class, [], 9);

    expect($ten - $one)->toBeLessThan(5);
});

it('lists the packs of a product with the same queries for one or ten', function () {
    $cups = Catalog::product(['Unidades' => ['100', '1000']]);
    $make = fn () => packWith([$cups, Catalog::product(['Unidades' => ['100']])]);
    $params = ['record' => $cups->getRouteKey()];

    $one = queriesToList(\Gongarce\ProductPacks\Filament\Resources\ProductResource\Pages\ManageProductPacks::class, $params, 1, $make);
    $ten = queriesToList(\Gongarce\ProductPacks\Filament\Resources\ProductResource\Pages\ManageProductPacks::class, $params, 9, $make);

    expect($ten - $one)->toBeLessThan(5);
});

it('puts the pack pages menu on the right, like the products', function () {
    expect(\Gongarce\ProductPacks\Filament\Resources\PackResource::getSubNavigationPosition())
        ->toBe(\Lunar\Admin\Filament\Resources\ProductResource::getSubNavigationPosition());
});
