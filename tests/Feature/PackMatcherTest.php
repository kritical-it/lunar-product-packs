<?php

use KriticalIt\ProductPacks\Discounts\MatchLine;
use KriticalIt\ProductPacks\Discounts\PackMatch;
use KriticalIt\ProductPacks\Discounts\PackMatcher;
use KriticalIt\ProductPacks\Enums\OptionMode;
use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Models\Pack;
use KriticalIt\ProductPacks\ProductPacks;
use KriticalIt\ProductPacks\Tests\Fixtures\Catalog;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;

/**
 * A cart line of the variant of the product that has these values (by name).
 */
function cartLine(int $id, Product $product, array $valueNames, int $quantity): MatchLine
{
    $product->load('variants.values');
    $variant = $product->variants->first(fn ($variant) => $variant->values->map(fn ($value) => $value->translate('name'))->sort()->values()->all() === collect($valueNames)->sort()->values()->all());

    return new MatchLine($id, $product->id, $variant->id, $variant->values->modelKeys(), $quantity);
}

function discountedMatch(array $lines): array
{
    return (new PackMatcher)->match(collect($lines), (new ProductPacks)->discounted());
}

beforeEach(fn () => installPackDiscount());

function discountPack(Pack $pack, float $percentage = 7.0): Pack
{
    $pack->update(['discount_percentage' => $percentage, 'discount_enabled' => true]);

    return $pack;
}

it('matches nothing when the record of the discount has ended', function () {
    ['cups' => $cups, 'lids' => $lids, 'pack' => $pack] = cupsAndLids();
    discountPack($pack);
    Discount::where('handle', 'product-packs')->sole()->update(['ends_at' => now()->subDay()]);

    expect(discountedMatch([
        cartLine(1, $cups, ['100'], 1),
        cartLine(2, $lids, ['100'], 1),
    ]))->toBe([]);
});

it('matches one pack for 3 cups and 1 lid', function () {
    ['cups' => $cups, 'lids' => $lids, 'pack' => $pack, 'cupItem' => $cupItem] = cupsAndLids();
    $cupItem->update(['quantity' => 3]);
    discountPack($pack);

    $matches = discountedMatch([cartLine(1, $cups, ['100'], 3), cartLine(2, $lids, ['100'], 1)]);

    expect($matches)->toHaveCount(1)
        ->and($matches[0])->toBeInstanceOf(PackMatch::class)
        ->and($matches[0]->pack->id)->toBe($pack->id)
        ->and($matches[0]->count)->toBe(1)
        ->and($matches[0]->units)->toBe([1 => 3, 2 => 1]);
});

it('matches with the base quantities', function () {
    $tray = Catalog::product();
    $lids = Catalog::product();
    $pack = packWith([$tray, $lids], PackStatus::Published);
    $pack->items()->where('product_id', $lids->id)->update(['quantity' => 2]);
    discountPack($pack);

    $matches = discountedMatch([cartLine(1, $tray, [], 2), cartLine(2, $lids, [], 3)]);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->count)->toBe(1)
        ->and($matches[0]->units)->toBe([1 => 1, 2 => 2]);
});

it('matches each row of a link apart', function () {
    ['cups' => $cups, 'lids' => $lids, 'pack' => $pack] = cupsAndLidsBySize();
    discountPack($pack);

    $matches = discountedMatch([
        cartLine(1, $cups, ['240'], 2), cartLine(2, $cups, ['350'], 1),
        cartLine(3, $lids, ['80'], 1), cartLine(4, $lids, ['90'], 2),
    ]);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->count)->toBe(2)
        ->and($matches[0]->units)->toBe([1 => 1, 3 => 1, 2 => 1, 4 => 1]);
});

it('does not match a cup and a lid of different rows', function () {
    ['cups' => $cups, 'lids' => $lids, 'pack' => $pack] = cupsAndLidsBySize();
    discountPack($pack);

    expect(discountedMatch([cartLine(1, $cups, ['240'], 1), cartLine(2, $lids, ['90'], 1)]))->toBe([]);
});

it('mixes variants of a free option', function () {
    $napkins = Catalog::product(['Color' => ['Blanca', 'Negra']]);
    $cup = Catalog::product();
    $pack = packWith([$napkins, $cup], PackStatus::Published);
    $pack->items()->where('product_id', $napkins->id)->update(['quantity' => 10]);
    discountPack($pack);

    $matches = discountedMatch([
        cartLine(1, $napkins, ['Blanca'], 5), cartLine(2, $napkins, ['Negra'], 5), cartLine(3, $cup, [], 1),
    ]);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->count)->toBe(1)
        ->and($matches[0]->units)->toBe([1 => 5, 2 => 5, 3 => 1]);
});

it('respects fixed values', function () {
    $boxes = Catalog::product(['Unidades' => ['100', '1000']]);
    $lid = Catalog::product();
    $pack = packWith([$boxes, $lid], PackStatus::Published);
    $pack->items()->where('product_id', $boxes->id)->first()->options()->create([
        'product_option_id' => Catalog::option($boxes, 'Unidades')->id,
        'mode' => OptionMode::Fixed,
        'product_option_value_id' => Catalog::value($boxes, 'Unidades', '1000')->id,
    ]);
    discountPack($pack);

    expect(discountedMatch([cartLine(1, $boxes, ['100'], 1), cartLine(2, $lid, [], 1)]))->toBe([])
        ->and(discountedMatch([cartLine(1, $boxes, ['1000'], 1), cartLine(2, $lid, [], 1)]))->toHaveCount(1);
});

it('gives each unit to one pack, the highest percentage first', function () {
    $cup = Catalog::product();
    $lid = Catalog::product();
    $five = discountPack(packWith([$cup, $lid], PackStatus::Published), 5);
    $seven = discountPack(packWith([$cup, $lid], PackStatus::Published), 7);
    $otherSeven = discountPack(packWith([$cup, $lid], PackStatus::Published), 7);

    $matches = discountedMatch([cartLine(1, $cup, [], 1), cartLine(2, $lid, [], 1)]);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->pack->id)->toBe($seven->id)
        ->and($seven->id)->toBeLessThan($otherSeven->id);

    $matches = discountedMatch([cartLine(1, $cup, [], 2), cartLine(2, $lid, [], 2)]);

    expect(collect($matches)->map(fn ($match) => [$match->pack->id, $match->count])->all())->toBe([[$seven->id, 2]])
        ->and($five->id)->not->toBe($seven->id);
});

it('needs only the visible products', function () {
    $cup = Catalog::product();
    $lid = Catalog::product();
    $napkins = Catalog::product();
    $three = discountPack(packWith([$cup, $lid, $napkins], PackStatus::Published));
    $napkins->update(['status' => 'draft']);

    $lines = [cartLine(1, $cup, [], 1), cartLine(2, $lid, [], 1), cartLine(3, $napkins, [], 1)];
    $matches = discountedMatch($lines);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->units)->toBe([1 => 1, 2 => 1]);

    $lid->update(['status' => 'draft']);

    expect(discountedMatch($lines))->toBe([])
        ->and($three->id)->toBeInt();
});

it('ignores variants without the linked value', function () {
    ['cups' => $cups, 'lids' => $lids, 'pack' => $pack] = cupsAndLids();
    discountPack($pack);
    $option = Catalog::option($lids, 'Unidades');
    $value = ProductOptionValue::create(['product_option_id' => $option->id, 'name' => ['en' => '500'], 'position' => 3]);
    $variant = ProductVariant::factory()->create(['product_id' => $lids->id]);
    $variant->values()->attach($value->id);
    $lids = $lids->fresh();

    expect(discountedMatch([cartLine(1, $cups, ['100'], 1), cartLine(2, $lids, ['500'], 1)]))->toBe([]);
});

it('ignores a variant whose value was deleted', function () {
    ['cups' => $cups, 'lids' => $lids, 'pack' => $pack] = cupsAndLids();
    discountPack($pack);
    $lines = [cartLine(1, $cups, ['100'], 1), cartLine(2, $lids, ['100'], 1)];

    Catalog::value($lids, 'Unidades', '100')->delete();

    expect(discountedMatch($lines))->toBe([]);
});

it('returns nothing for packs without discount', function () {
    ['cups' => $cups, 'lids' => $lids] = cupsAndLids();

    expect(discountedMatch([cartLine(1, $cups, ['100'], 1), cartLine(2, $lids, ['100'], 1)]))->toBe([]);
});
