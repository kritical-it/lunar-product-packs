<?php

use KriticalIt\ProductPacks\Actions\AddPackToCart;
use KriticalIt\ProductPacks\Exceptions\PackLineRejected;
use KriticalIt\ProductPacks\Exceptions\PackNotAvailable;
use KriticalIt\ProductPacks\Exceptions\PackProductNotAvailable;
use KriticalIt\ProductPacks\Tests\Fixtures\Catalog;
use Lunar\Exceptions\Carts\CartException;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;

beforeEach(function () {
    Channel::factory()->create(['default' => true]);
    $this->cart = Cart::factory()->create(['currency_id' => Currency::getDefault()->id]);
    $this->setup = cupsAndLids();
});

function linesOf(array $setup, int $cupQuantity = 1, int $lidQuantity = 1): array
{
    return [
        ['variantId' => $setup['cups']->variants->first()->id, 'quantity' => $cupQuantity],
        ['variantId' => $setup['lids']->variants->first()->id, 'quantity' => $lidQuantity],
    ];
}

it('adds every line', function () {
    $cart = app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup, 2, 3));

    expect($cart->lines)->toHaveCount(2)
        ->and($cart->lines->pluck('quantity')->sort()->values()->all())->toBe([2, 3]);
});

it('adds to the line of the same variant already in the cart', function () {
    $this->cart->add($this->setup['cups']->variants->first(), 4);

    $cart = app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup, 2, 3));

    expect($cart->lines)->toHaveCount(2)
        ->and($cart->lines->firstWhere('purchasable_id', $this->setup['cups']->variants->first()->id)->quantity)->toBe(6);
});

it('passes the given meta to every line', function () {
    $cart = app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup), ['list' => 'pack_1']);

    expect($cart->lines->map(fn ($line) => (array) $line->meta)->all())->each->toBe(['list' => 'pack_1']);
});

it('rejects a variant of a product outside the pack', function () {
    $other = Catalog::product();

    expect(fn () => app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], [
        ...linesOf($this->setup),
        ['variantId' => $other->variants->first()->id, 'quantity' => 1],
    ]))->toThrow(PackProductNotAvailable::class);

    expect($this->cart->fresh()->lines)->toHaveCount(0);
});

it('adds nothing when one line fails', function () {
    $this->setup['lids']->variants->first()->update(['stock' => 0, 'backorder' => 0, 'purchasable' => 'in_stock']);

    expect(fn () => app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup)))
        ->toThrow(CartException::class);

    expect($this->cart->fresh()->lines)->toHaveCount(0);
});

it('names the line that fails, and whether for its stock', function () {
    $lid = $this->setup['lids']->variants->first();
    $lid->update(['stock' => 2, 'backorder' => 0, 'purchasable' => 'in_stock']);

    try {
        app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup, 1, 5));
        $this->fail('The pack was added');
    } catch (PackLineRejected $e) {
        expect($e->variant->id)->toBe($lid->id)
            ->and($e->quantity)->toBe(5)
            ->and($e->isStock())->toBeTrue();
    }
});

it('tells a line refused by its quantity from one without stock', function () {
    $lid = $this->setup['lids']->variants->first();
    $lid->update(['quantity_increment' => 10]);

    try {
        app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup, 1, 5));
        $this->fail('The pack was added');
    } catch (PackLineRejected $e) {
        expect($e->variant->id)->toBe($lid->id)
            ->and($e->isStock())->toBeFalse();
    }

    expect($this->cart->fresh()->lines)->toHaveCount(0);
});

it('rejects a pack that is not published', function () {
    $this->setup['pack']->update(['status' => \KriticalIt\ProductPacks\Enums\PackStatus::Draft]);

    expect(fn () => app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup)))
        ->toThrow(PackNotAvailable::class);
});

it('rejects a product left out of the pack', function () {
    $this->setup['lids']->update(['status' => 'draft']);

    expect(fn () => app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup)))
        ->toThrow(PackProductNotAvailable::class);

    expect($this->cart->fresh()->lines)->toHaveCount(0);
});
