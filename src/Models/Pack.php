<?php

namespace Gongarce\ProductPacks\Models;

use Gongarce\ProductPacks\Database\Factories\PackFactory;
use Gongarce\ProductPacks\Enums\PackStatus;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Base\BaseModel;
use Lunar\Base\Traits\HasTranslations;

/**
 * Products bought together: each with its base quantity, and each of its
 * options fixed, linked or free.
 *
 * @property int $id
 * @property ?\Illuminate\Support\Collection $title translatable, optional
 * @property PackStatus $status
 */
class Pack extends BaseModel
{
    use HasFactory;
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'title' => AsCollection::class,
        'status' => PackStatus::class,
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function newFactory(): PackFactory
    {
        return PackFactory::new();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackItem::modelClass())->orderBy('position')->orderBy('id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(PackLink::modelClass())->orderBy('position')->orderBy('id');
    }
}
