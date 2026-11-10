<?php

use KriticalIt\ProductPacks\Enums\OptionMode;
use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Models\Pack;
use KriticalIt\ProductPacks\Tests\Fixtures\Catalog;
use KriticalIt\ProductPacks\Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(\KriticalIt\ProductPacks\Tests\LongPrefixTestCase::class)->in('Prefixed');

/**
 * Cups and lids linked by units, 100 ↔ 100 and 1000 ↔ 1000.
 */
function cupsAndLids(): array
{
    $cups = Catalog::product(['Unidades' => ['100', '1000']]);
    $lids = Catalog::product(['Unidades' => ['100', '1000']]);

    $pack = Pack::create(['title' => ['en' => 'Cups with lids'], 'status' => PackStatus::Published]);
    $cupItem = $pack->items()->create(['product_id' => $cups->id, 'position' => 1]);
    $lidItem = $pack->items()->create(['product_id' => $lids->id, 'position' => 2]);
    $link = $pack->links()->create(['position' => 1]);
    $cupUnits = $cupItem->options()->create(['product_option_id' => Catalog::option($cups, 'Unidades')->id, 'mode' => OptionMode::Linked, 'pack_link_id' => $link->id]);
    $lidUnits = $lidItem->options()->create(['product_option_id' => Catalog::option($lids, 'Unidades')->id, 'mode' => OptionMode::Linked, 'pack_link_id' => $link->id]);

    foreach (['100', '1000'] as $i => $units) {
        $row = $link->rows()->create(['position' => $i + 1]);
        $row->values()->create(['pack_item_option_id' => $cupUnits->id, 'product_option_value_id' => Catalog::value($cups, 'Unidades', $units)->id]);
        $row->values()->create(['pack_item_option_id' => $lidUnits->id, 'product_option_value_id' => Catalog::value($lids, 'Unidades', $units)->id]);
    }

    return compact('cups', 'lids', 'pack', 'cupItem', 'lidItem', 'link');
}

/**
 * Only fake the plugin event: Eloquent model events must keep running.
 */
function fakePackEvents(): void
{
    \Illuminate\Support\Facades\Event::fake([\KriticalIt\ProductPacks\Events\PackChanged::class]);
}

/**
 * @return \Illuminate\Support\Collection<int, \KriticalIt\ProductPacks\Events\PackChanged>
 */
function packEvents(?\KriticalIt\ProductPacks\Events\PackChangeReason $reason = null)
{
    return \Illuminate\Support\Facades\Event::dispatched(\KriticalIt\ProductPacks\Events\PackChanged::class)
        ->map(fn (array $args) => $args[0])
        ->filter(fn ($event) => $reason === null || $event->reason === $reason)
        ->values();
}

/**
 * Union of the product ids of every dispatched event, optionally by reason.
 *
 * @return list<int>
 */
function packProductIds(?\KriticalIt\ProductPacks\Events\PackChangeReason $reason = null): array
{
    return \KriticalIt\ProductPacks\Events\PackChanged::normalizeIds(packEvents($reason)->flatMap(fn ($event) => $event->productIds));
}

/**
 * A pack with the given products, in that order, and no rules.
 */
function packWith(array $products, \KriticalIt\ProductPacks\Enums\PackStatus $status = \KriticalIt\ProductPacks\Enums\PackStatus::Draft): \KriticalIt\ProductPacks\Models\Pack
{
    $pack = \KriticalIt\ProductPacks\Models\Pack::create(['status' => $status]);
    foreach ($products as $i => $product) {
        $pack->items()->create(['product_id' => $product->id, 'position' => $i + 1]);
    }

    return $pack;
}
