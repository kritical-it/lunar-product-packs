<?php

namespace KriticalIt\ProductPacks\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Base\BaseModel;
use Lunar\Models\ProductOptionValue;

/**
 * @property int $id
 * @property int $pack_link_row_id
 * @property int $pack_item_option_id the column
 * @property int $product_option_value_id
 */
class PackLinkRowValue extends BaseModel
{
    protected $guarded = [];

    public function row(): BelongsTo
    {
        return $this->belongsTo(PackLinkRow::modelClass(), 'pack_link_row_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(PackItemOption::modelClass(), 'pack_item_option_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ProductOptionValue::modelClass(), 'product_option_value_id');
    }
}
