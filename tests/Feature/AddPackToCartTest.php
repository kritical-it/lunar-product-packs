<?php

use Gongarce\ProductPacks\Actions\AddPackToCart;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;
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

it('adds every line with the pack in its meta', function () {
    $cart = app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup, 2, 3));

    expect($cart->lines)->toHaveCount(2)
        ->and($cart->lines->pluck('quantity')->sort()->values()->all())->toBe([2, 3])
        ->and($cart->lines->map(fn ($line) => (array) $line->meta)->all())->each->toMatchArray(['pack' => $this->setup['pack']->id]);
});

it('merges extra meta', function () {
    $cart = app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup), ['list' => 'pack_1']);

    expect((array) $cart->lines->first()->meta)->toMatchArray(['pack' => $this->setup['pack']->id, 'list' => 'pack_1']);
});

it('rejects a variant of a product outside the pack', function () {
    $other = Catalog::product();

    expect(fn () => app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], [
        ...linesOf($this->setup),
        ['variantId' => $other->variants->first()->id, 'quantity' => 1],
    ]))->toThrow(InvalidArgumentException::class);

    expect($this->cart->fresh()->lines)->toHaveCount(0);
});

it('adds nothing when one line fails', function () {
    $this->setup['lids']->variants->first()->update(['stock' => 0, 'backorder' => 0, 'purchasable' => 'in_stock']);

    expect(fn () => app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup)))
        ->toThrow(CartException::class);

    expect($this->cart->fresh()->lines)->toHaveCount(0);
});

it('rejects a pack that is not published', function () {
    $this->setup['pack']->update(['status' => \Gongarce\ProductPacks\Enums\PackStatus::Draft]);

    expect(fn () => app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup)))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a product left out of the pack', function () {
    $this->setup['lids']->update(['status' => 'draft']);

    expect(fn () => app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], linesOf($this->setup)))
        ->toThrow(InvalidArgumentException::class);

    expect($this->cart->fresh()->lines)->toHaveCount(0);
});
