<?php

namespace KriticalIt\ProductPacks\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Models\Pack;
use KriticalIt\ProductPacks\Models\PackItem;
use Lunar\Models\ProductAssociation;

/**
 * Turn each cross-sell association into a pack of two products: quantities
 * of 1, every option free, shown on the page of the product it came from (on
 * both when the association goes both ways). The associations stay.
 *
 * Pairs that already have a pack with exactly those two products are skipped,
 * so it can run again.
 */
class ImportCrossSellPacks extends Command
{
    protected $signature = 'lunar:packs:import-cross-sell {--dry-run : Only report how many packs it would create}';

    protected $description = 'Create a pack of two products for each cross-sell association';

    public function handle(): int
    {
        $pairs = $this->pairs();
        $existing = $this->existingPairs();
        $new = array_diff_key($pairs, $existing);

        $this->info(sprintf('%d cross-sell pairs, %d already as packs, %d new packs.', count($pairs), count($pairs) - count($new), count($new)));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($new) {
            foreach ($new as $pair) {
                $pack = Pack::modelClass()::create(['status' => PackStatus::Published]);
                foreach ($pair as $position => [$productId, $shown]) {
                    $pack->items()->create([
                        'product_id' => $productId,
                        'quantity' => 1,
                        'show_on_product' => $shown,
                        'position' => $position + 1,
                    ]);
                }
            }
        });

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{0: array{int, bool}, 1: array{int, bool}}> by "lowId-highId"
     */
    private function pairs(): array
    {
        $pairs = [];

        ProductAssociation::modelClass()::query()
            ->where('type', ProductAssociation::CROSS_SELL)
            ->orderBy('id')
            ->get(['product_parent_id', 'product_target_id'])
            ->each(function ($association) use (&$pairs) {
                [$parent, $target] = [$association->product_parent_id, $association->product_target_id];

                if ($parent === $target) {
                    return;
                }
                $key = self::key($parent, $target);

                if (isset($pairs[$key])) {
                    // The other way round too: shown on both.
                    $pairs[$key][1][1] = true;

                    return;
                }

                $pairs[$key] = [[$parent, true], [$target, false]];
            });

        return $pairs;
    }

    /**
     * @return array<string, true> packs of exactly two products, by "lowId-highId"
     */
    private function existingPairs(): array
    {
        return PackItem::modelClass()::query()
            ->get(['pack_id', 'product_id'])
            ->groupBy('pack_id')
            ->filter(fn ($items) => $items->count() === 2)
            ->mapWithKeys(fn ($items) => [self::key(...$items->pluck('product_id')->all()) => true])
            ->all();
    }

    private static function key(int $a, int $b): string
    {
        return min($a, $b).'-'.max($a, $b);
    }
}
