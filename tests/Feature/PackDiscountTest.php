<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use KriticalIt\ProductPacks\Actions\AddPackToCart;
use KriticalIt\ProductPacks\Discounts\PackDiscount;
use KriticalIt\ProductPacks\Events\PackChanged;
use KriticalIt\ProductPacks\Events\PackChangeReason;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\ProductVariant;

beforeEach(function () {
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    $this->artisan('product-packs:install-discount')->assertSuccessful();

    $this->setup = cupsAndLids();
    $this->setup['pack']->update(['discount_percentage' => 7, 'discount_enabled' => true]);
    $this->cup = $this->setup['cups']->variants->first();
    $this->lid = $this->setup['lids']->variants->first();
    $this->cart = Cart::factory()->create(['currency_id' => Currency::getDefault()->id]);
});

function lineOf(Cart $cart, ProductVariant $variant): CartLine
{
    return $cart->lines->firstWhere('purchasable_id', $variant->id);
}

it('discounts the complete packs of the cart', function () {
    $cart = app(AddPackToCart::class)->execute($this->cart, $this->setup['pack'], [
        ['variantId' => $this->cup->id, 'quantity' => 1],
        ['variantId' => $this->lid->id, 'quantity' => 1],
    ], ['list' => 'pack_1'])->recalculate();

    expect($cart->discountTotal->value)->toBe(140)
        ->and(lineOf($cart, $this->cup)->subTotalDiscounted->value)->toBe(930)
        ->and(lineOf($cart, $this->lid)->subTotalDiscounted->value)->toBe(930)
        ->and(lineOf($cart, $this->cup)->discountTotal->value)->toBe(70);
});

it('discounts only the units of complete packs', function () {
    $this->cart->add($this->cup, 3);
    $cart = $this->cart->add($this->lid, 1);

    expect($cart->discountTotal->value)->toBe(140)
        ->and(lineOf($cart, $this->cup)->subTotal->value)->toBe(3000)
        ->and(lineOf($cart, $this->cup)->subTotalDiscounted->value)->toBe(2930)
        ->and(lineOf($cart, $this->lid)->subTotalDiscounted->value)->toBe(930);
});

it('discounts lines added one by one, without the meta of a pack', function () {
    $this->cart->add($this->cup, 2);
    $cart = $this->cart->add($this->lid, 2);

    expect($cart->lines->every(fn (CartLine $line) => blank((array) $line->meta)))->toBeTrue()
        ->and($cart->discountTotal->value)->toBe(280);
});

it('does not discount variants that do not make a pack', function () {
    $this->cart->add($this->cup, 1);
    // The lid of 1000 does not go with the cup of 100.
    $cart = $this->cart->add($this->setup['lids']->variants->last(), 1);

    expect($cart->discountTotal->value)->toBe(0);
});

it('stops discounting when a product of the pack leaves the cart', function () {
    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);
    expect($cart->discountTotal->value)->toBe(140);

    $cart = $cart->remove(lineOf($cart, $this->lid)->id);
    expect($cart->discountTotal->value)->toBe(0)
        ->and(lineOf($cart, $this->cup)->subTotalDiscounted->value)->toBe(1000);

    $cart = $cart->add($this->lid, 1);
    expect($cart->discountTotal->value)->toBe(140);
});

it('adds to a percentage coupon', function () {
    Discount::factory()->create([
        'type' => AmountOff::class,
        'coupon' => 'FIVE',
        'data' => ['fixed_value' => false, 'percentage' => 5],
        'starts_at' => now()->subDay(),
    ]);
    $this->cart->update(['coupon_code' => 'FIVE']);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    expect(lineOf($cart, $this->cup)->discountTotal->value)->toBe(120)
        ->and(lineOf($cart, $this->cup)->subTotalDiscounted->value)->toBe(880)
        ->and(lineOf($cart, $this->lid)->discountTotal->value)->toBe(120)
        ->and($cart->discountTotal->value)->toBe(240);
});

it('applies once with two records of its type', function () {
    Discount::factory()->create(['type' => PackDiscount::class, 'priority' => 0, 'starts_at' => now()->subDay()]);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    expect($cart->discountTotal->value)->toBe(140)
        ->and($cart->discountBreakdown)->toHaveCount(1);
});

it('adds a breakdown per pack', function () {
    $other = cupsAndLidsBySize();
    $other['pack']->update(['discount_percentage' => 10, 'discount_enabled' => true]);
    $otherCup = $other['cups']->variants->first();
    $otherLid = $other['lids']->variants->first();

    $this->cart->add($this->cup, 3);
    $this->cart->add($this->lid, 2);
    $this->cart->add($otherCup, 1);
    $cart = $this->cart->add($otherLid, 1);

    $breakdowns = $cart->discountBreakdown->values();
    $summary = $breakdowns->map(fn ($breakdown) => [
        'price' => $breakdown->price->value,
        'discount' => $breakdown->discount->handle,
        'lines' => $breakdown->lines->mapWithKeys(fn ($line) => [$line->line->purchasable_id => $line->quantity])->all(),
    ])->all();

    // The highest percentage first.
    expect($summary)->toBe([
        ['price' => 200, 'discount' => 'product-packs', 'lines' => [$otherCup->id => 1, $otherLid->id => 1]],
        ['price' => 280, 'discount' => 'product-packs', 'lines' => [$this->cup->id => 2, $this->lid->id => 2]],
    ])->and($cart->discountTotal->value)->toBe(480);
});

it('does not discount a pack whose discount is disabled', function () {
    $this->setup['pack']->update(['discount_enabled' => false]);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    expect($cart->discountTotal->value)->toBe(0);
});

it('keeps the packs for the request until a pack changes', function () {
    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);
    expect($cart->discountTotal->value)->toBe(140);

    // Without an event, the packs already loaded stay.
    DB::table($this->setup['pack']->getTable())->update(['discount_percentage' => 10]);
    expect($cart->recalculate()->discountTotal->value)->toBe(140);

    PackChanged::dispatch([], PackChangeReason::Saved, $this->setup['pack']->id);
    expect($cart->recalculate()->discountTotal->value)->toBe(200);
});

it('opens the discount in the panel', function () {
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    $this->asStaff();

    Livewire::test(EditDiscount::class, ['record' => Discount::where('handle', 'product-packs')->sole()->getRouteKey()])
        ->assertSuccessful()
        ->assertSee(__('product-packs::panel.discount.help'));
});
