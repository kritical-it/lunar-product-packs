<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use KriticalIt\ProductPacks\Enums\OptionMode;
use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Events\PackChanged;
use KriticalIt\ProductPacks\Events\PackChangeReason;
use KriticalIt\ProductPacks\Models\Pack;
use KriticalIt\ProductPacks\Tests\Fixtures\Catalog;
use KriticalIt\ProductPacks\Tests\LongPrefixTestCase;
use KriticalIt\ProductPacks\Tests\TestCase;
use Lunar\Models\Channel;
use Lunar\Models\CustomerGroup;

uses(TestCase::class)->in('Feature');
uses(LongPrefixTestCase::class)->in('Prefixed');

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
    Event::fake([PackChanged::class]);
}

/**
 * @return Collection<int, PackChanged>
 */
function packEvents(?PackChangeReason $reason = null)
{
    return Event::dispatched(PackChanged::class)
        ->map(fn (array $args) => $args[0])
        ->filter(fn ($event) => $reason === null || $event->reason === $reason)
        ->values();
}

/**
 * Union of the product ids of every dispatched event, optionally by reason.
 *
 * @return list<int>
 */
function packProductIds(?PackChangeReason $reason = null): array
{
    return PackChanged::normalizeIds(packEvents($reason)->flatMap(fn ($event) => $event->productIds));
}

/**
 * A pack with the given products, in that order, and no rules.
 */
function packWith(array $products, PackStatus $status = PackStatus::Draft): Pack
{
    $pack = Pack::create(['status' => $status]);
    foreach ($products as $i => $product) {
        $pack->items()->create(['product_id' => $product->id, 'position' => $i + 1]);
    }

    return $pack;
}

/**
 * Cups and lids linked by size, 240 ↔ 80 and 350 ↔ 90.
 */
function cupsAndLidsBySize(): array
{
    $cups = Catalog::product(['Tamaño' => ['240', '350']]);
    $lids = Catalog::product(['Tamaño' => ['80', '90']]);

    $pack = Pack::create(['title' => ['en' => 'Cups by size'], 'status' => PackStatus::Published]);
    $cupItem = $pack->items()->create(['product_id' => $cups->id, 'position' => 1]);
    $lidItem = $pack->items()->create(['product_id' => $lids->id, 'position' => 2]);
    $link = $pack->links()->create(['position' => 1]);
    $cupSize = $cupItem->options()->create(['product_option_id' => Catalog::option($cups, 'Tamaño')->id, 'mode' => OptionMode::Linked, 'pack_link_id' => $link->id]);
    $lidSize = $lidItem->options()->create(['product_option_id' => Catalog::option($lids, 'Tamaño')->id, 'mode' => OptionMode::Linked, 'pack_link_id' => $link->id]);

    foreach ([['240', '80'], ['350', '90']] as $i => [$cup, $lid]) {
        $row = $link->rows()->create(['position' => $i + 1]);
        $row->values()->create(['pack_item_option_id' => $cupSize->id, 'product_option_value_id' => Catalog::value($cups, 'Tamaño', $cup)->id]);
        $row->values()->create(['pack_item_option_id' => $lidSize->id, 'product_option_value_id' => Catalog::value($lids, 'Tamaño', $lid)->id]);
    }

    return compact('cups', 'lids', 'pack', 'cupItem', 'lidItem', 'link');
}

/**
 * A default channel and customer group, and the «Packs» discount on them.
 */
function installPackDiscount(): void
{
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    test()->artisan('product-packs:install-discount')->assertSuccessful();
}
