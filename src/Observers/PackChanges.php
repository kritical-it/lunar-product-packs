<?php

namespace Gongarce\ProductPacks\Observers;

use Gongarce\ProductPacks\Events\PackChanged;
use Gongarce\ProductPacks\Events\PackChangeReason;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Gongarce\ProductPacks\Models\PackItemOption;
use Gongarce\ProductPacks\Models\PackLink;
use Gongarce\ProductPacks\Models\PackLinkRow;
use Gongarce\ProductPacks\Models\PackLinkRowValue;
use Illuminate\Database\Eloquent\Model;

/**
 * Dispatches PackChanged from the model events of every table of a pack.
 *
 * Deleting a pack removes the rest by database cascade, which fires no model
 * events: that is a single event, with the products captured before.
 */
class PackChanges
{
    /** @var array<int, list<int>> products of the packs being deleted, by pack id */
    private static array $deleting = [];

    public static function register(): void
    {
        $pack = Pack::modelClass();
        // A model instance keeps wasRecentlyCreated after later saves: created and
        // updated (which only fires with changes) tell them apart.
        $pack::created(fn (Pack $pack) => self::dispatch($pack->getKey(), PackChangeReason::Saved));
        $pack::updated(function (Pack $pack) {
            if ($pack->wasChanged(['title', 'status'])) {
                self::dispatch($pack->getKey(), PackChangeReason::Saved);
            }
        });
        $pack::deleting(fn (Pack $pack) => self::$deleting[$pack->getKey()] = self::productIds($pack->getKey()));
        $pack::deleted(function (Pack $pack) {
            $ids = self::$deleting[$pack->getKey()] ?? [];
            unset(self::$deleting[$pack->getKey()]);
            PackChanged::dispatch($ids, PackChangeReason::Deleted, $pack->getKey());
        });

        $item = PackItem::modelClass();
        $item::created(fn (PackItem $item) => self::dispatch($item->pack_id, PackChangeReason::ItemsChanged));
        $item::updated(fn (PackItem $item) => self::dispatch($item->pack_id, PackChangeReason::ItemsChanged, [$item->getOriginal('product_id')]));
        $item::deleted(fn (PackItem $item) => self::dispatch($item->pack_id, PackChangeReason::ItemsChanged, [$item->product_id]));

        $options = [
            PackItemOption::modelClass() => fn (PackItemOption $rule) => PackItem::modelClass()::whereKey($rule->pack_item_id)->value('pack_id'),
            PackLink::modelClass() => fn (PackLink $link) => $link->pack_id,
            PackLinkRow::modelClass() => fn (PackLinkRow $row) => PackLink::modelClass()::whereKey($row->pack_link_id)->value('pack_id'),
            PackLinkRowValue::modelClass() => fn (PackLinkRowValue $value) => PackLink::modelClass()::query()
                ->whereKey(PackLinkRow::modelClass()::whereKey($value->pack_link_row_id)->value('pack_link_id'))
                ->value('pack_id'),
        ];

        foreach ($options as $model => $packId) {
            $model::created(fn (Model $model) => self::dispatch($packId($model), PackChangeReason::OptionsChanged));
            $model::updated(fn (Model $model) => self::dispatch($packId($model), PackChangeReason::OptionsChanged));
            $model::deleted(fn (Model $model) => self::dispatch($packId($model), PackChangeReason::OptionsChanged));
        }
    }

    /**
     * @param  list<int|null>  $extra  products no longer in the pack
     */
    private static function dispatch(?int $packId, PackChangeReason $reason, array $extra = []): void
    {
        if ($packId === null || isset(self::$deleting[$packId])) {
            return;
        }

        PackChanged::dispatch([...self::productIds($packId), ...$extra], $reason, $packId);
    }

    /**
     * @return list<int>
     */
    private static function productIds(int $packId): array
    {
        return PackItem::modelClass()::where('pack_id', $packId)->pluck('product_id')->all();
    }
}
