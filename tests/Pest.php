<?php

use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;
use Gongarce\ProductPacks\Tests\TestCase;

uses(TestCase::class)->in('Feature');

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
