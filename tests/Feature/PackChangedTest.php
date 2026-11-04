<?php

use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Events\PackChanged;
use Gongarce\ProductPacks\Events\PackChangeReason;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;

function sorted(int ...$ids): array
{
    sort($ids);

    return $ids;
}

beforeEach(function () {
    $this->setup = cupsAndLids();
    fakePackEvents();
});

it('reports the products of a saved pack', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids] = $this->setup;

    $pack->update(['status' => PackStatus::Draft]);

    expect(packEvents())->toHaveCount(1)
        ->and(packEvents()->first()->reason)->toBe(PackChangeReason::Saved)
        ->and(packEvents()->first()->packId)->toBe($pack->id)
        ->and(packProductIds())->toBe(sorted($cups->id, $lids->id));
});

it('does not report a save that changes nothing', function () {
    $this->setup['pack']->save();

    expect(packEvents())->toHaveCount(0);
});

it('reports an added, moved or removed product with the pack products', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids, 'lidItem' => $lidItem] = $this->setup;
    $napkins = Catalog::product();
    $towels = Catalog::product();

    $item = $pack->items()->create(['product_id' => $napkins->id, 'position' => 3]);
    expect(packProductIds(PackChangeReason::ItemsChanged))->toBe(sorted($cups->id, $lids->id, $napkins->id));

    fakePackEvents();
    $item->update(['product_id' => $towels->id]);
    expect(packProductIds(PackChangeReason::ItemsChanged))->toBe(sorted($cups->id, $lids->id, $napkins->id, $towels->id));

    fakePackEvents();
    $lidItem->delete();
    expect(packProductIds(PackChangeReason::ItemsChanged))->toBe(sorted($cups->id, $lids->id, $towels->id));
});

it('reports a changed rule, link, row or value with the pack products', function () {
    ['cups' => $cups, 'lids' => $lids, 'link' => $link, 'cupItem' => $cupItem] = $this->setup;
    $expected = sorted($cups->id, $lids->id);

    $changes = [
        fn () => $cupItem->options()->first()->update(['mode' => OptionMode::Fixed, 'product_option_value_id' => Catalog::value($cups, 'Unidades', '100')->id, 'pack_link_id' => null]),
        fn () => $link->update(['position' => 5]),
        fn () => $link->rows()->first()->update(['position' => 9]),
        fn () => $link->rows()->first()->values()->first()->delete(),
        fn () => $link->rows()->first()->delete(),
    ];

    foreach ($changes as $change) {
        fakePackEvents();
        $change();

        expect(packEvents(PackChangeReason::OptionsChanged))->not->toBeEmpty()
            ->and(packProductIds(PackChangeReason::OptionsChanged))->toBe($expected);
    }
});

it('reports a deleted pack once, with the products it had', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids] = $this->setup;

    $pack->delete();

    expect(packEvents())->toHaveCount(1)
        ->and(packEvents()->first()->reason)->toBe(PackChangeReason::Deleted)
        ->and(packProductIds())->toBe(sorted($cups->id, $lids->id));
});

it('normalizes the product ids', fn () => expect(PackChanged::normalizeIds([3, '1', null, '', 3]))->toBe([1, 3]));

it('reports a pack built in one transaction once', function () {
    fakePackEvents();

    \Illuminate\Support\Facades\DB::transaction(fn () => cupsAndLids());

    expect(packEvents())->toHaveCount(1)
        ->and(packEvents()->first()->productIds)->toHaveCount(2);
});

it('forgets the changes of a rolled back transaction', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids] = $this->setup;

    try {
        \Illuminate\Support\Facades\DB::transaction(function () use ($pack) {
            $pack->update(['status' => PackStatus::Draft]);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    $pack->refresh()->update(['title' => ['en' => 'Renamed']]);

    expect(packEvents())->toHaveCount(1)
        ->and(packProductIds())->toBe(sorted($cups->id, $lids->id));
});
