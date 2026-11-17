<?php

use KriticalIt\ProductPacks\Discounts\PackDiscount;
use KriticalIt\ProductPacks\Pipelines\Order\Creation\MapPackDiscounts;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Facades\Discounts;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\Tag;

beforeEach(function () {
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    $this->artisan('product-packs:install-discount')->assertSuccessful();

    $this->setup = cupsAndLids();
    $this->setup['pack']->update(['discount_percentage' => 7, 'discount_enabled' => true]);
    $this->cup = $this->setup['cups']->variants->first();
    $this->lid = $this->setup['lids']->variants->first();
    $this->cart = Cart::factory()->create(['currency_id' => Currency::getDefault()->id]);
    $this->cart->add($this->cup, 1);
    $this->cart = $this->cart->add($this->lid, 1);
    $this->order = Order::factory()->create(['cart_id' => $this->cart->id]);
    $this->order->setRelation('cart', $this->cart);
});

function runMapPackDiscounts(Order $order): Order
{
    return app(MapPackDiscounts::class)->handle($order, fn ($o) => $o);
}

/** A fixed coupon on the cart of the order, which comes fresh as after a refresh. */
function fixedCoupon(int $amount): void
{
    $coupon = Discount::factory()->create([
        'type' => AmountOff::class,
        'coupon' => 'FIXED',
        'data' => ['fixed_value' => true, 'fixed_values' => [test()->cart->currency->code => $amount]],
        'starts_at' => now()->subDay(),
    ]);
    // On every channel: the order factory makes another one the default.
    PackDiscount::makeAvailable($coupon, replace: true);
    test()->cart->update(['coupon_code' => 'FIXED']);
    Discounts::resetDiscounts();
    test()->order->setRelation('cart', Cart::find(test()->cart->id));
}

it('records the packs of the order', function () {
    $order = runMapPackDiscounts($this->order);

    $packs = $order->fresh()->meta['pack_discounts'];

    expect($packs)->toHaveCount(1)
        ->and($packs[0]['pack_id'])->toBe($this->setup['pack']->id)
        ->and($packs[0]['percentage'])->toEqual(7.0)
        ->and($packs[0]['count'])->toBe(1)
        ->and($packs[0]['lines'])->toHaveCount(2)
        ->and($packs[0]['lines'][0])->toMatchArray(['quantity' => 1, 'discount' => 70])
        ->and(collect($packs[0]['lines'])->pluck('purchasable_id')->sort()->values()->all())
        ->toBe(collect([$this->cup->id, $this->lid->id])->sort()->values()->all());
});

it('leaves the tags of the order to the shop', function () {
    $this->order->tags()->attach(Tag::firstOrCreate(['value' => 'WEB']));

    runMapPackDiscounts($this->order);

    expect($this->order->tags()->pluck('value')->all())->toBe(['WEB']);
});

it('removes the meta when the cart has no pack any more', function () {
    runMapPackDiscounts($this->order);

    $cart = $this->cart->remove($this->cart->lines->firstWhere('purchasable_id', $this->lid->id)->id);
    $this->order->setRelation('cart', $cart);
    $order = runMapPackDiscounts($this->order);

    expect($order->fresh()->meta)->not->toHaveKey('pack_discounts');
});

it('records what the cart took off, only what a fixed coupon left of each line', function () {
    fixedCoupon(1900);

    $order = runMapPackDiscounts($this->order);

    // The coupon leaves 50 of each line; the pack would take 70.
    expect(collect($order->fresh()->meta['pack_discounts'][0]['lines'])->pluck('discount')->all())->toBe([50, 50]);
});

it('records nothing when a fixed coupon leaves nothing to take off', function () {
    fixedCoupon(2000);

    $order = runMapPackDiscounts($this->order);

    expect($order->fresh()->meta)->not->toHaveKey('pack_discounts');
});

it('records the packs when the cart of the order comes fresh, as after a refresh of the order', function () {
    $this->order->setRelation('cart', Cart::find($this->cart->id));

    $order = runMapPackDiscounts($this->order);

    expect($order->fresh()->meta['pack_discounts'][0]['lines'][0])->toMatchArray(['quantity' => 1, 'discount' => 70]);
});

it('records nothing when the pack discount record is not active', function () {
    Discount::query()->where('handle', PackDiscount::HANDLE)
        ->update(['ends_at' => now()->subDay()]);
    Discounts::resetDiscounts();
    $this->order->setRelation('cart', Cart::find($this->cart->id));

    $order = runMapPackDiscounts($this->order);

    expect($order->fresh()->meta)->not->toHaveKey('pack_discounts');
});
