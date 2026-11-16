<?php

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use KriticalIt\ProductPacks\Discounts\PackDiscount;
use Lunar\Models\Channel;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;

/**
 * @return array<int, array{enabled: bool, visible?: bool, active: bool}>
 */
function availability(BelongsToMany $relation, bool $visible = false): array
{
    return $relation->get()->mapWithKeys(fn ($model) => [$model->id => [
        'enabled' => (bool) $model->pivot->enabled,
        ...($visible ? ['visible' => (bool) $model->pivot->visible] : []),
        'active' => $model->pivot->starts_at !== null && $model->pivot->ends_at === null,
    ]])->sortKeys()->all();
}

it('creates the discount once', function () {
    $this->artisan('product-packs:install-discount')->assertSuccessful();
    $this->artisan('product-packs:install-discount')->assertSuccessful();

    $discount = Discount::sole();
    expect($discount->only('handle', 'name', 'type', 'priority', 'coupon', 'data'))->toBe([
        'handle' => 'product-packs',
        'name' => 'Packs',
        'type' => PackDiscount::class,
        'priority' => 0,
        'coupon' => null,
        'data' => [],
    ])->and($discount->starts_at->isPast() || $discount->starts_at->isNow())->toBeTrue();
});

it('attaches every channel and customer group', function () {
    $channels = [Channel::factory()->create(['default' => true]), Channel::factory()->create(['default' => false])];
    $groups = [CustomerGroup::factory()->create(['default' => true]), CustomerGroup::factory()->create(['default' => false])];

    $this->artisan('product-packs:install-discount')->assertSuccessful();

    $discount = Discount::sole();
    $on = ['enabled' => true, 'active' => true];
    expect(availability($discount->channels()))->toBe([$channels[0]->id => $on, $channels[1]->id => $on])
        ->and(availability($discount->customerGroups(), visible: true))->toBe([
            $groups[0]->id => ['enabled' => true, 'visible' => true, 'active' => true],
            $groups[1]->id => ['enabled' => true, 'visible' => true, 'active' => true],
        ]);
});

it('attaches on a later run what is missing, and leaves what is there', function () {
    $channel = Channel::factory()->create(['default' => true]);
    $this->artisan('product-packs:install-discount')->assertSuccessful();
    $discount = Discount::sole();
    $discount->channels()->updateExistingPivot($channel->id, ['enabled' => false]);

    // Without the plugin listener, as if the channel came before the plugin.
    $other = Channel::withoutEvents(fn () => Channel::factory()->create());
    expect($discount->channels()->allRelatedIds()->all())->not->toContain($other->id);

    $this->artisan('product-packs:install-discount')->assertSuccessful();

    expect(availability($discount->channels()))->toBe([
        $channel->id => ['enabled' => false, 'active' => true],
        $other->id => ['enabled' => true, 'active' => true],
    ]);
});

it('attaches channels and customer groups created later', function () {
    $this->artisan('product-packs:install-discount')->assertSuccessful();
    $discount = Discount::sole();

    $channel = Channel::factory()->create();
    $group = CustomerGroup::factory()->create();

    expect(availability($discount->channels()))->toBe([$channel->id => ['enabled' => true, 'active' => true]])
        ->and(availability($discount->customerGroups(), visible: true))->toBe([
            $group->id => ['enabled' => true, 'visible' => true, 'active' => true],
        ]);
});

it('removes the records of its type, with what they have attached', function () {
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    $this->artisan('product-packs:install-discount')->assertSuccessful();
    $record = Discount::where('handle', PackDiscount::HANDLE)->sole();
    $other = Discount::factory()->create();

    PackDiscount::removeRecords();

    expect(Discount::pluck('id')->all())->toBe([$other->id])
        ->and($record->channels()->count())->toBe(0)
        ->and($record->customerGroups()->count())->toBe(0);
});
