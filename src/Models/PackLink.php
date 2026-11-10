<?php

namespace KriticalIt\ProductPacks\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Base\BaseModel;

/**
 * Options of different products of a pack paired by hand: a column per
 * linked option, a row per pair (cups 100 ↔ lids 100). A value in no row is
 * not offered by the pack.
 *
 * @property int $id
 * @property int $pack_id
 * @property int $position
 */
class PackLink extends BaseModel
{
    protected $guarded = [];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::modelClass());
    }

    /** The linked options, in the order of their products in the pack. */
    public function columns(): HasMany
    {
        return $this->hasMany(PackItemOption::modelClass(), 'pack_link_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PackLinkRow::modelClass())->orderBy('position')->orderBy('id');
    }
}
