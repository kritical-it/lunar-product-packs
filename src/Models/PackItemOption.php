<?php

namespace Gongarce\ProductPacks\Models;

use Gongarce\ProductPacks\Enums\OptionMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Base\BaseModel;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;

/**
 * The rule of one option of a product in a pack: a fixed value, or a column
 * of a link. Options without a rule are free.
 *
 * @property int $id
 * @property int $pack_item_id
 * @property int $product_option_id
 * @property OptionMode $mode
 * @property ?int $product_option_value_id only when fixed
 * @property ?int $pack_link_id only when linked
 */
class PackItemOption extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'mode' => OptionMode::class,
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PackItem::modelClass(), 'pack_item_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::modelClass(), 'product_option_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ProductOptionValue::modelClass(), 'product_option_value_id');
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(PackLink::modelClass(), 'pack_link_id');
    }
}
