<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use KriticalIt\ProductPacks\Actions\AddPackToCart;
use KriticalIt\ProductPacks\Discounts\CartPackDiscounts;
use KriticalIt\ProductPacks\Discounts\PackDiscount;
use KriticalIt\ProductPacks\Discounts\PackDiscountRecord;
use KriticalIt\ProductPacks\Events\PackChanged;
use KriticalIt\ProductPacks\Events\PackChangeReason;
use KriticalIt\ProductPacks\ProductPacks;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\ProductVariant;

beforeEach(function () {
    installPackDiscount();

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

it('never takes more than what is left of a line after a fixed coupon', function () {
    Discount::factory()->create([
        'type' => AmountOff::class,
        'coupon' => 'ALL',
        'data' => ['fixed_value' => true, 'fixed_values' => [Currency::getDefault()->code => 2000]],
        'starts_at' => now()->subDay(),
    ]);
    $this->cart->update(['coupon_code' => 'ALL']);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    expect($cart->lines->every(fn (CartLine $line) => $line->discountTotal->value <= $line->subTotal->value))->toBeTrue()
        ->and(lineOf($cart, $this->cup)->subTotalDiscounted->value)->toBe(0)
        ->and($cart->discountTotal->value)->toBe(2000)
        ->and($cart->discountTotal->value)->toBeLessThanOrEqual($cart->subTotal->value);
});

it('takes off only what is left of a line when the coupon leaves less than the pack', function () {
    Discount::factory()->create([
        'type' => AmountOff::class,
        'coupon' => 'MOST',
        'data' => ['fixed_value' => true, 'fixed_values' => [Currency::getDefault()->code => 1900]],
        'starts_at' => now()->subDay(),
    ]);
    $this->cart->update(['coupon_code' => 'MOST']);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    // The coupon leaves 50 of each line; the pack would take 70.
    expect(lineOf($cart, $this->cup)->discountTotal->value)->toBe(1000)
        ->and(lineOf($cart, $this->cup)->subTotalDiscounted->value)->toBe(0)
        ->and($cart->discountTotal->value)->toBe(2000);
});

it('does not discount when the plugin is turned off', function () {
    config(['lunar.product-packs.enabled' => false]);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    expect($cart->discountTotal->value)->toBe(0)
        ->and($cart->discountBreakdown ?? collect())->toBeEmpty();
});

it('gives nothing when the record of the discount has ended', function () {
    Discount::where('handle', PackDiscount::HANDLE)->sole()->update(['ends_at' => now()->subDay()]);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    expect($cart->discountTotal->value)->toBe(0)
        ->and(app(ProductPacks::class)->discounted())->toBeEmpty()
        ->and(app(CartPackDiscounts::class)->for($cart))->toBe([]);
});

it('checks the record of the discount once per request, until a discount is saved', function () {
    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);
    expect($cart->discountTotal->value)->toBe(140);

    $record = Discount::where('handle', PackDiscount::HANDLE)->sole();
    DB::table($record->getTable())->where('id', $record->id)->update(['ends_at' => now()->subDay()]);
    expect(app(PackDiscountRecord::class)->usable())->toBeTrue();

    $record->fresh()->save();
    expect(app(PackDiscountRecord::class)->usable())->toBeFalse()
        ->and($cart->recalculate()->discountTotal->value)->toBe(0);
});

it('applies once with two records of its type', function () {
    Discount::factory()->create(['type' => PackDiscount::class, 'priority' => 0, 'starts_at' => now()->subDay()]);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    expect($cart->discountTotal->value)->toBe(140)
        ->and($cart->discountBreakdown)->toHaveCount(1);
});

it('runs after a coupon whatever the priority given to a record of its type', function () {
    Discount::factory()->create([
        'type' => AmountOff::class,
        'coupon' => 'FIVE',
        'data' => ['fixed_value' => false, 'percentage' => 5],
        'starts_at' => now()->subDay(),
    ]);
    $other = Discount::factory()->create(['type' => PackDiscount::class, 'priority' => 10, 'starts_at' => now()->subDay()]);
    $this->cart->update(['coupon_code' => 'FIVE']);

    $this->cart->add($this->cup, 1);
    $cart = $this->cart->add($this->lid, 1);

    expect($other->fresh()->priority)->toBe(0)
        ->and(lineOf($cart, $this->cup)->discountTotal->value)->toBe(120)
        ->and(lineOf($cart, $this->lid)->discountTotal->value)->toBe(120)
        ->and($cart->discountTotal->value)->toBe(240);
});

it('keeps priority 0 when a record changes to its type', function () {
    $discount = Discount::factory()->create(['type' => AmountOff::class, 'priority' => 5]);

    $discount->update(['type' => PackDiscount::class, 'priority' => 3]);

    expect($discount->fresh()->priority)->toBe(0);
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
