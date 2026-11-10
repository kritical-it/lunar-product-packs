<?php

use Filament\Facades\Filament;
use KriticalIt\ProductPacks\Filament\Resources\PackResource\Pages\ManagePackOptions;
use KriticalIt\ProductPacks\Models\PackLinkRow;
use KriticalIt\ProductPacks\Tests\Fixtures\Catalog;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    $this->asStaff();
});

function optionsPage($pack)
{
    return Livewire::test(ManagePackOptions::class, ['record' => $pack->getRouteKey()]);
}

function choiceField($item, $product, string $option, string $field = 'choice'): string
{
    return "item_{$item->id}.option_".Catalog::option($product, $option)->id.".{$field}";
}

it('shows one choice per option the product has', function () {
    ['pack' => $pack, 'cups' => $cups, 'lids' => $lids, 'cupItem' => $cupItem, 'lidItem' => $lidItem] = cupsAndLids();
    $cups->productOptions()->detach(Catalog::option($cups, 'Unidades')->id);

    optionsPage($pack)
        ->assertFormFieldExists(choiceField($lidItem, $lids, 'Unidades'), 'rulesForm')
        ->assertFormFieldDoesNotExist("item_{$cupItem->id}.option_".$cupItem->options()->sole()->product_option_id.'.choice', 'rulesForm')
        ->assertFormSet([choiceField($lidItem, $lids, 'Unidades') => 'link:A'], 'rulesForm');
});

it('links the units of cups and lids', function () {
    $cups = Catalog::product(['Unidades' => ['100', '1000']]);
    $lids = Catalog::product(['Unidades' => ['100', '1000']]);
    $pack = packWith([$cups, $lids]);
    [$cupItem, $lidItem] = $pack->items->all();

    $page = optionsPage($pack)
        ->fillForm([
            choiceField($cupItem, $cups, 'Unidades') => 'link:A',
            choiceField($lidItem, $lids, 'Unidades') => 'link:A',
        ], 'rulesForm')
        ->call('saveRules')
        ->assertHasNoFormErrors(formName: 'rulesForm');

    $link = $pack->links()->sole();
    expect($page->get('links'))->toHaveKey("link_{$link->id}")
        ->and($page->get("links.link_{$link->id}.rows"))->toBe([]);
});

it('requires the value of a fixed option', function () {
    ['pack' => $pack, 'lids' => $lids, 'lidItem' => $lidItem] = cupsAndLids();

    optionsPage($pack)
        ->fillForm([choiceField($lidItem, $lids, 'Unidades') => 'fixed'], 'rulesForm')
        ->call('saveRules')
        ->assertHasFormErrors([choiceField($lidItem, $lids, 'Unidades', 'value') => 'required'], 'rulesForm');
});

it('proposes rows by name and saves them', function () {
    ['pack' => $pack, 'link' => $link] = cupsAndLids();
    $link->rows()->get()->each->delete();
    fakePackEvents();

    $page = optionsPage($pack)->call('proposeRows', $link->id);

    expect($page->get("links.link_{$link->id}.rows"))->toHaveCount(2)
        ->and(PackLinkRow::count())->toBe(0);

    $page->call('saveLinks')->assertHasNoErrors();

    expect($link->rows()->count())->toBe(2)
        ->and(packEvents())->toHaveCount(1);
});

it('rejects duplicate rows', function () {
    ['pack' => $pack, 'link' => $link] = cupsAndLids();
    $page = optionsPage($pack);
    $rows = $page->get("links.link_{$link->id}.rows");
    $first = reset($rows);

    $page->set("links.link_{$link->id}.rows", ['a' => $first, 'b' => $first])
        ->call('saveLinks')
        ->assertHasErrors(["links.link_{$link->id}.rows"]);

    expect($link->rows()->count())->toBe(2);
});

it('shows the validator errors', function () {
    ['pack' => $pack, 'link' => $link] = cupsAndLids();
    $link->rows()->get()->each->delete();

    optionsPage($pack)->assertSee(__('product-packs::validation.link_without_rows'));
});

it('shows a pack with a deleted product and keeps its rules', function () {
    ['pack' => $pack, 'lids' => $lids, 'lidItem' => $lidItem] = cupsAndLids();
    $lids->delete();

    optionsPage($pack)->assertOk()->call('saveRules')->assertHasNoErrors();

    expect($lidItem->options()->count())->toBe(1);
});

it('refuses to save the rules of a pack changed since the page was opened', function () {
    ['pack' => $pack, 'cupItem' => $cupItem] = cupsAndLids();
    $page = optionsPage($pack);
    $sleeves = Catalog::product(['Unidades' => ['100']]);
    $sleeveItem = $pack->items()->create(['product_id' => $sleeves->id, 'position' => 3]);
    $sleeveItem->options()->create([
        'product_option_id' => Catalog::option($sleeves, 'Unidades')->id,
        'mode' => \KriticalIt\ProductPacks\Enums\OptionMode::Fixed,
        'product_option_value_id' => Catalog::value($sleeves, 'Unidades', '100')->id,
    ]);

    $page->call('saveRules');

    expect($sleeveItem->options()->count())->toBe(1)
        ->and($cupItem->options()->count())->toBe(1);
});

it('keeps unsaved options when saving the links', function () {
    ['pack' => $pack, 'lids' => $lids, 'lidItem' => $lidItem] = cupsAndLids();

    $page = optionsPage($pack)
        ->fillForm([choiceField($lidItem, $lids, 'Unidades') => 'free'], 'rulesForm')
        ->call('saveLinks');

    expect($page->get('rules.'.choiceField($lidItem, $lids, 'Unidades')))->toBe('free');
});
