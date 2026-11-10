<?php

namespace KriticalIt\ProductPacks\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KriticalIt\ProductPacks\Database\Factories\PackItemFactory;
use Lunar\Base\BaseModel;
use Lunar\Models\Product;

/**
 * A product of a pack, with its base quantity (the proportion in the pack)
 * and whether the pack shows on its own product page.
 *
 * @property int $id
 * @property int $pack_id
 * @property int $product_id
 * @property int $quantity
 * @property bool $show_on_product
 * @property int $position
 */
class PackItem extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'show_on_product' => 'boolean',
        'position' => 'integer',
    ];

    protected $attributes = [
        'quantity' => 1,
        'show_on_product' => true,
        'position' => 1,
    ];

    protected static function newFactory(): PackItemFactory
    {
        return PackItemFactory::new();
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::modelClass());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::modelClass());
    }

    public function options(): HasMany
    {
        return $this->hasMany(PackItemOption::modelClass());
    }
}
