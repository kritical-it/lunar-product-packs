<?php

use KriticalIt\ProductPacks\Models\Pack;

it('is active only when enabled with a percentage between 0 and 100', function (?float $percentage, bool $enabled, bool $active) {
    $pack = Pack::factory()->create(['discount_percentage' => $percentage, 'discount_enabled' => $enabled]);

    expect($pack->fresh()->hasActiveDiscount())->toBe($active);
})->with([
    'enabled' => [7.0, true, true],
    'disabled' => [7.0, false, false],
    'without percentage' => [null, true, false],
    'zero' => [0, true, false],
    'one hundred' => [100, true, false],
]);

it('builds a discounted pack with the factory state', function () {
    $pack = Pack::factory()->discounted(12.5, true)->create()->fresh();

    expect($pack->hasActiveDiscount())->toBeTrue()
        ->and($pack->discount_percentage)->toBe('12.50')
        ->and($pack->discount_badge)->toBeTrue();
});
