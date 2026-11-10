<?php

namespace KriticalIt\ProductPacks\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched (after commit) whenever the packs shown on one or more product
 * pages may have changed. The plugin knows nothing of routes or caches: the
 * storefront decides what to do with the products.
 */
final readonly class PackChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @var list<int>
     */
    public array $productIds;

    /**
     * @param  iterable<int|string|null>  $productIds
     */
    public function __construct(
        iterable $productIds,
        public PackChangeReason $reason,
        public ?int $packId = null,
    ) {
        $this->productIds = self::normalizeIds($productIds);
    }

    /**
     * @param  iterable<int|string|null>  $ids
     * @return list<int>
     */
    public static function normalizeIds(iterable $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }

            $normalized[(int) $id] = (int) $id;
        }

        sort($normalized);

        return array_values($normalized);
    }
}
