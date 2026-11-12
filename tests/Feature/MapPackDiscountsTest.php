<?php

use KriticalIt\ProductPacks\Discounts\PackDiscount;
use KriticalIt\ProductPacks\Pipelines\Order\Creation\MapPackDiscounts;
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

it('tags the order without touching its other tags', function () {
    $this->order->tags()->attach(Tag::firstOrCreate(['value' => 'WEB']));

    runMapPackDiscounts($this->order);

    expect($this->order->tags()->pluck('value')->sort()->values()->all())->toBe(['PACK', 'WEB']);
});

it('removes the tag and the meta when the cart has no pack any more', function () {
    $this->order->tags()->attach(Tag::firstOrCreate(['value' => 'WEB']));
    runMapPackDiscounts($this->order);

    $cart = $this->cart->remove($this->cart->lines->firstWhere('purchasable_id', $this->lid->id)->id);
    $this->order->setRelation('cart', $cart);
    $order = runMapPackDiscounts($this->order);

    expect($order->fresh()->meta)->not->toHaveKey('pack_discounts')
        ->and($order->tags()->pluck('value')->all())->toBe(['WEB']);
});

it('keeps one tag when run twice', function () {
    runMapPackDiscounts($this->order);
    runMapPackDiscounts($this->order);

    expect($this->order->tags()->where('value', 'PACK')->count())->toBe(1);
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

    expect($order->fresh()->meta)->not->toHaveKey('pack_discounts')
        ->and($order->tags()->where('value', 'PACK')->exists())->toBeFalse();
});
