<?php

namespace KriticalIt\ProductPacks\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use KriticalIt\ProductPacks\Discounts\PackDiscount;
use Lunar\Models\Discount;

/**
 * Create the Lunar discount record that applies the % of the packs, once,
 * and attach it to every channel and customer group it does not have yet.
 * It can run again: what is attached stays as it is.
 */
class InstallPackDiscount extends Command
{
    protected $signature = 'product-packs:install-discount';

    protected $description = 'Create the discount that applies the percentage of each pack';

    public function handle(): int
    {
        DB::transaction(function () {
            $discount = Discount::modelClass()::firstOrCreate(['handle' => PackDiscount::HANDLE], [
                'name' => 'Packs',
                'type' => PackDiscount::class,
                'priority' => 0,
                'starts_at' => now(),
                'data' => [],
                'coupon' => null,
            ]);

            // Lunar attaches a new discount to every channel and group, enabled
            // only on the default ones: on creation, all of them are enabled.
            PackDiscount::makeAvailable($discount, replace: $discount->wasRecentlyCreated);

            $this->info($discount->wasRecentlyCreated ? 'Pack discount created.' : 'Pack discount already there; channels and customer groups attached.');
        });

        return self::SUCCESS;
    }
}
