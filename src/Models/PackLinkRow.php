<?php

namespace Gongarce\ProductPacks\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Base\BaseModel;

/**
 * One pair of a link: a value for each of its columns.
 *
 * @property int $id
 * @property int $pack_link_id
 * @property int $position
 */
class PackLinkRow extends BaseModel
{
    protected $guarded = [];

    public function link(): BelongsTo
    {
        return $this->belongsTo(PackLink::modelClass(), 'pack_link_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(PackLinkRowValue::modelClass());
    }
}
