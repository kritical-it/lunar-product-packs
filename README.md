# Lunar Product Packs

Products bought together, as an entity of their own, for [Lunar](https://lunarphp.io).

A cross-sell association only says that one product goes with another. A pack says
**how** they go together:

- **Two or more products**, each with its **base quantity**: 10 boxes of cups with
  1 pack of lids.
- **Every option of every product** is one of three:
  - **Fixed**: always the same value ("this pack is of 1000 units").
  - **Linked** to options of other products of the pack, through a table of pairs
    made by hand: cup of 240 ml ↔ lid of Ø 80 mm, 100 units ↔ 100 units. A value
    that is in no row is not offered in that pack.
  - **Free**: the customer chooses it.
- **Shown on the page of each of its products**, or only on some of them: a pack
  is created once and shows on every page that should offer it.
- **Published or draft.**

The plugin holds the data, the admin in the Lunar panel and the logic to add a
pack to a cart. The storefront renders the packs its own way: the plugin gives
it a definition ready to use and tells it when a pack changes, so it can forget
its cached pages.

## Requirements

- PHP `^8.2`
- LunarPHP `~1.4.0`

## Installation

```bash
composer require kriticalit/lunar-product-packs
php artisan migrate
```

The service provider is discovered by Laravel. It loads the migrations, unless
`lunar.database.disable_migrations` is set, and the tables take the Lunar table
prefix (`lunar_packs`, `lunar_pack_items`…).

Then register the plugin in the Lunar panel:

```php
use KriticalIt\ProductPacks\ProductPacksPlugin;
use Lunar\Admin\Support\Facades\LunarPanel;

public function register(): void
{
    LunarPanel::panel(fn ($panel) => $panel->plugin(ProductPacksPlugin::make()))
        ->register();
}
```

### Configuration

The configuration is merged under `lunar.product-packs`. The plugin can be
turned off without uninstalling it:

```dotenv
LUNAR_PRODUCT_PACKS_ENABLED=false
```

## The admin

- **Packs section**, under the catalog: list, create, edit and delete packs, with
  their title, status and products in order. Each product of a pack has its base
  quantity and whether the pack shows on its page.
- **Options and links page** of each pack: the mode of each option (fixed,
  linked or free) and the rows of each link. It proposes the pairs whose values
  are named alike, to be reviewed before saving.
- **Packs tab on the product**: the packs the product is in.
- **Checks before publishing:** a pack with errors can't be published, for
  example a fixed option without a value or a link without rows
  (`PackValidator`). The list and the edit page show them.

Managing packs takes the `catalog:manage-products` permission.

## The storefront

### Packs of a product page

```php
use KriticalIt\ProductPacks\PackDefinition;
use KriticalIt\ProductPacks\ProductPacks;

$packs = app(ProductPacks::class)->forProduct($product);

$definitions = $packs->map(fn ($pack) => app(PackDefinition::class)->make($pack, $product));
```

`forProduct` returns the published packs that show on that product's page, in
the order they were created. In each pack:

- only the products that are published and not deleted are kept;
- a pack left with fewer than two products is dropped;
- so is a pack whose fixed option lost its value.

`PackDefinition::make` gives a pack as a storefront needs it, starting with the
product of the page:

```php
[
    'id' => 3,
    'title' => 'Tarrina y tapa', // or null
    'items' => [
        [
            'id' => 7,             // the pack item
            'productId' => 120,
            'quantity' => 10,      // base quantity
            'current' => true,     // the product of the page
            'options' => [
                ['optionId' => 4, 'mode' => 'fixed', 'valueId' => 31],
                ['optionId' => 5, 'mode' => 'linked', 'linkId' => 2],
                // free options have no rule
            ],
        ],
        // ...
    ],
    'links' => [
        [
            'id' => 2,
            'columns' => [['itemId' => 7, 'optionId' => 5], ['itemId' => 8, 'optionId' => 9]],
            'rows' => [[40, 52], [41, 53]], // a value id per column
        ],
    ],
]
```

The definition only carries what a storefront can use. Rules on options a
product no longer has are dropped, and so are rows left without a value for
some column.

### Adding a pack to the cart

```php
use KriticalIt\ProductPacks\Actions\AddPackToCart;
use Lunar\Facades\CartSession;

app(AddPackToCart::class)->execute(CartSession::manager(), $pack, [
    ['variantId' => 301, 'quantity' => 10],
    ['variantId' => 415, 'quantity' => 1],
], meta: ['source' => 'pack']);
```

It adds all the lines or none. They are added in one transaction, so a line
Lunar refuses (stock, quantity rules) adds nothing. A variant added from a pack
goes to the same cart line as when it is added from its own page, with the
given `meta` on every line.

When it can't add the pack, it says why:

| Exception | When |
| --- | --- |
| `Exceptions\PackNotAvailable` | The pack is not published |
| `Exceptions\PackProductNotAvailable` | A variant of a product the pack does not show (`$e->variantId`) |
| `Exceptions\PackLineRejected` | Lunar refuses a line: `$e->variant`, `$e->quantity`, and `$e->isStock()` to tell stock from a quantity rule |

The first two extend `InvalidArgumentException`. `PackLineRejected` extends
Lunar's `CartException`.

### Events

Whenever what a page shows of a pack may have changed, the plugin dispatches
`KriticalIt\ProductPacks\Events\PackChanged` with the affected products. Products
that were taken out of the pack are included, because their pages showed it.

```php
use KriticalIt\ProductPacks\Events\PackChanged;

Event::listen(function (PackChanged $event) {
    $event->productIds; // list<int>: unique ids of every affected product
    $event->reason;     // PackChangeReason
    $event->packId;     // ?int, may point to an already deleted pack
});
```

| Reason | When |
| --- | --- |
| `Saved` | A pack is created, or its title or status changes |
| `ItemsChanged` | A product is added, changed or taken out |
| `OptionsChanged` | The mode of an option, a link or its rows change |
| `Deleted` | A pack is deleted (its products captured before the cascade) |

It implements `ShouldDispatchAfterCommit`. Changes within a transaction add up
into a single event, sent when it commits, so building a pack row by row is one
event and not dozens. A rollback sends nothing.

The relation `Product::packItems()` is added to Lunar's product, for example to
find the pages that show a product inside a pack.

## Importing the cross-sell associations

```bash
php artisan lunar:packs:import-cross-sell --dry-run   # only report how many
php artisan lunar:packs:import-cross-sell
```

It creates a published pack of two products for each cross-sell association.
Quantities are 1, every option is free, and the pack shows on the page of the
product the association comes from (on both pages when the association goes
both ways). The associations stay. Pairs that already have a pack with exactly
those two products are skipped, so the command can run again.

## Extending the models

The models (`Pack`, `PackItem`, `PackItemOption`, `PackLink`, `PackLinkRow`,
`PackLinkRowValue`) are registered in Lunar's model manifest. They can be
replaced like Lunar's own, and the plugin resolves them with `modelClass()`.

## Testing

```bash
composer install
composer test
```

## License

MIT
