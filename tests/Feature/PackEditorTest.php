<?php

use Gongarce\ProductPacks\Editing\PackEditor;
use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Models\PackItemOption;
use Gongarce\ProductPacks\Models\PackLink;
use Gongarce\ProductPacks\Models\PackLinkRow;
use Gongarce\ProductPacks\PackValidator;
use Gongarce\ProductPacks\Tests\Fixtures\Catalog;

/**
 * The rows of a link as [ruleId => valueId] maps, in order.
 */
function rowsOf(PackLink $link): array
{
    return $link->fresh()->rows->map(fn (PackLinkRow $row) => $row->values->pluck('product_option_value_id', 'pack_item_option_id')->sortKeys()->all())->all();
}

function ruleOf($item, $product, string $option): ?PackItemOption
{
    return $item->options()->where('product_option_id', Catalog::option($product, $option)->id)->first();
}

it('links two options through a new link', function () {
    $cups = Catalog::product(['Unidades' => ['100', '1000']]);
    $lids = Catalog::product(['Unidades' => ['100', '1000']]);
    $pack = packWith([$cups, $lids]);
    [$cupItem, $lidItem] = $pack->items->all();

    app(PackEditor::class)->saveRules($pack, [
        $cupItem->id => [Catalog::option($cups, 'Unidades')->id => ['choice' => 'link:A']],
        $lidItem->id => [Catalog::option($lids, 'Unidades')->id => ['choice' => 'link:A']],
    ]);

    $link = $pack->links()->sole();
    expect($link->columns->pluck('product_option_id')->sort()->values()->all())
        ->toBe([Catalog::option($cups, 'Unidades')->id, Catalog::option($lids, 'Unidades')->id])
        ->and($link->columns->every(fn ($rule) => $rule->mode === OptionMode::Linked))->toBeTrue()
        ->and($link->rows)->toHaveCount(0);
});

it('fixes an option and drops its values from the rows', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids, 'cupItem' => $cupItem, 'lidItem' => $lidItem, 'link' => $link] = cupsAndLids();
    $cupRule = ruleOf($cupItem, $cups, 'Unidades');

    app(PackEditor::class)->saveRules($pack, [
        $cupItem->id => [Catalog::option($cups, 'Unidades')->id => ['choice' => 'link:A']],
        $lidItem->id => [Catalog::option($lids, 'Unidades')->id => ['choice' => 'fixed', 'value' => Catalog::value($lids, 'Unidades', '1000')->id]],
    ]);

    $lidRule = ruleOf($lidItem, $lids, 'Unidades');
    expect($lidRule->mode)->toBe(OptionMode::Fixed)
        ->and($lidRule->product_option_value_id)->toBe(Catalog::value($lids, 'Unidades', '1000')->id)
        ->and($lidRule->pack_link_id)->toBeNull()
        ->and(rowsOf($link))->toBe([
            [$cupRule->id => Catalog::value($cups, 'Unidades', '100')->id],
            [$cupRule->id => Catalog::value($cups, 'Unidades', '1000')->id],
        ])
        ->and(app(PackValidator::class)->errors($pack->fresh()))->toContain(__('product-packs::validation.link_with_one_column'));
});

it('frees an option', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids, 'cupItem' => $cupItem, 'lidItem' => $lidItem] = cupsAndLids();

    app(PackEditor::class)->saveRules($pack, [
        $cupItem->id => [Catalog::option($cups, 'Unidades')->id => ['choice' => 'link:A']],
        $lidItem->id => [Catalog::option($lids, 'Unidades')->id => ['choice' => 'free']],
    ]);

    expect(ruleOf($lidItem, $lids, 'Unidades'))->toBeNull()
        ->and(ruleOf($cupItem, $cups, 'Unidades'))->not->toBeNull();
});

it('deletes the rules of options the product no longer has', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids, 'cupItem' => $cupItem, 'lidItem' => $lidItem] = cupsAndLids();
    $cupOption = Catalog::option($cups, 'Unidades');
    $cups->productOptions()->detach($cupOption->id);

    app(PackEditor::class)->saveRules($pack, [
        $cupItem->id => [],
        $lidItem->id => [Catalog::option($lids, 'Unidades')->id => ['choice' => 'link:A']],
    ]);

    expect($cupItem->options()->where('product_option_id', $cupOption->id)->exists())->toBeFalse();
});

it('deletes links left without columns and renumbers the rest', function () {
    $cups = Catalog::product(['Unidades' => ['100'], 'Tamaño' => ['240 ml']]);
    $lids = Catalog::product(['Unidades' => ['100'], 'Tamaño' => ['240 ml']]);
    $pack = packWith([$cups, $lids]);
    [$cupItem, $lidItem] = $pack->items->all();
    $editor = app(PackEditor::class);
    $choices = fn (string $units, string $size) => [
        $cupItem->id => [Catalog::option($cups, 'Unidades')->id => ['choice' => $units], Catalog::option($cups, 'Tamaño')->id => ['choice' => $size]],
        $lidItem->id => [Catalog::option($lids, 'Unidades')->id => ['choice' => $units], Catalog::option($lids, 'Tamaño')->id => ['choice' => $size]],
    ];

    $editor->saveRules($pack, $choices('link:A', 'link:B'));
    $sizes = $pack->links()->where('position', 2)->sole();

    $editor->saveRules($pack, $choices('free', 'link:B'));

    $link = $pack->links()->sole();
    expect($link->id)->toBe($sizes->id)
        ->and($link->position)->toBe(1);
});

it('replaces the rows of a link', function () {
    ['cups' => $cups, 'lids' => $lids, 'cupItem' => $cupItem, 'lidItem' => $lidItem, 'link' => $link] = cupsAndLids();
    $cupRule = ruleOf($cupItem, $cups, 'Unidades');
    $lidRule = ruleOf($lidItem, $lids, 'Unidades');
    $rows = [
        [$cupRule->id => Catalog::value($cups, 'Unidades', '1000')->id, $lidRule->id => Catalog::value($lids, 'Unidades', '1000')->id],
        [$cupRule->id => Catalog::value($cups, 'Unidades', '100')->id, $lidRule->id => Catalog::value($lids, 'Unidades', '1000')->id],
    ];
    fakePackEvents();

    app(PackEditor::class)->saveRows($link, $rows);

    expect(rowsOf($link))->toBe($rows)
        ->and(packEvents())->toHaveCount(1);
});

it('tidies a pack after removing a product', function () {
    $cups = Catalog::product(['Unidades' => ['100', '1000']]);
    $lids = Catalog::product(['Unidades' => ['100', '1000']]);
    $sleeves = Catalog::product(['Unidades' => ['100', '1000']]);
    $pack = packWith([$cups, $lids, $sleeves]);
    [$cupItem, $lidItem, $sleeveItem] = $pack->items->all();
    $editor = app(PackEditor::class);
    $editor->saveRules($pack, [
        $cupItem->id => [Catalog::option($cups, 'Unidades')->id => ['choice' => 'link:A']],
        $lidItem->id => [Catalog::option($lids, 'Unidades')->id => ['choice' => 'link:A']],
        $sleeveItem->id => [Catalog::option($sleeves, 'Unidades')->id => ['choice' => 'link:A']],
    ]);
    $link = $pack->links()->sole();
    $cupRule = ruleOf($cupItem, $cups, 'Unidades');
    $lidRule = ruleOf($lidItem, $lids, 'Unidades');
    $sleeveRule = ruleOf($sleeveItem, $sleeves, 'Unidades');
    $hundred = fn ($product) => Catalog::value($product, 'Unidades', '100')->id;
    $editor->saveRows($link, [
        [$cupRule->id => $hundred($cups), $lidRule->id => $hundred($lids), $sleeveRule->id => $hundred($sleeves)],
        [$cupRule->id => $hundred($cups), $lidRule->id => $hundred($lids), $sleeveRule->id => Catalog::value($sleeves, 'Unidades', '1000')->id],
    ]);

    $sleeveItem->delete();
    $editor->tidy($pack);

    expect(rowsOf($link))->toBe([[$cupRule->id => $hundred($cups), $lidRule->id => $hundred($lids)]]);

    $cupItem->delete();
    $lidItem->delete();
    $editor->tidy($pack);

    expect($pack->links()->count())->toBe(0);
});

it('proposes the rows whose values have the same name', function () {
    $cups = Catalog::product(['Tamaño' => ['240 ml', '350 ml', '500 ml']]);
    $lids = Catalog::product(['Tamaño' => ['240  ML ', '350 ml']]);
    $pack = packWith([$cups, $lids]);
    [$cupItem, $lidItem] = $pack->items->all();
    $editor = app(PackEditor::class);
    $editor->saveRules($pack, [
        $cupItem->id => [Catalog::option($cups, 'Tamaño')->id => ['choice' => 'link:A']],
        $lidItem->id => [Catalog::option($lids, 'Tamaño')->id => ['choice' => 'link:A']],
    ]);
    $link = $pack->links()->sole();
    $cupRule = ruleOf($cupItem, $cups, 'Tamaño');
    $lidRule = ruleOf($lidItem, $lids, 'Tamaño');
    $existing = [[$lidRule->id => Catalog::value($lids, 'Tamaño', '350 ml')->id, $cupRule->id => Catalog::value($cups, 'Tamaño', '350 ml')->id]];

    $proposed = $editor->proposeRows($link, $existing);

    expect($proposed)->toBe([[
        $cupRule->id => Catalog::value($cups, 'Tamaño', '240 ml')->id,
        $lidRule->id => Catalog::value($lids, 'Tamaño', '240  ML ')->id,
    ]]);
});
