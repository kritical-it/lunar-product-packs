<?php

namespace KriticalIt\ProductPacks\Discounts;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use KriticalIt\ProductPacks\Enums\OptionMode;
use KriticalIt\ProductPacks\Models\Pack;
use KriticalIt\ProductPacks\Models\PackItem;
use KriticalIt\ProductPacks\Models\PackItemOption;
use KriticalIt\ProductPacks\ProductPacks;

/**
 * Finds the complete packs among the lines of a cart. Greedy: each unit goes
 * to the first pack that can use it, in the order the packs come (the
 * caller sorts them, highest discount first); it does not look for the best
 * split.
 *
 * A pack is complete with, for each product, its base quantity of variants
 * that follow its rules: the fixed values, and the same row of each link.
 * Free options accept any variant, so they mix.
 */
class PackMatcher
{
    /**
     * @param  Collection<int, MatchLine>  $lines
     * @param  Collection<int, Pack>  $packs  with ProductPacks::RELATIONS loaded
     * @return list<PackMatch> only those with at least one pack
     */
    public function match(Collection $lines, Collection $packs): array
    {
        $lines = $lines->sortBy('id')->values();
        $remaining = $lines->pluck('quantity', 'id')->all();
        $matches = [];

        foreach ($packs as $pack) {
            $match = $this->matchPack($pack, $lines, $remaining);

            if ($match) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    /**
     * @param  Collection<int, MatchLine>  $lines
     * @param  array<int, int>  $remaining  line id → units not yet taken, updated
     */
    private function matchPack(Pack $pack, Collection $lines, array &$remaining): ?PackMatch
    {
        $items = ProductPacks::visibleItems($pack)
            ->filter(fn (PackItem $item) => $item->quantity > 0)
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        // Lines of each item that follow its fixed values.
        $candidates = $items->map(fn (PackItem $item) => $lines
            ->where('productId', $item->product_id)
            ->filter(fn (MatchLine $line) => $this->rules($item)
                ->every(fn (PackItemOption $rule) => $rule->mode !== OptionMode::Fixed
                    || in_array($rule->product_option_value_id, $line->valueIds, true)))
            ->values());

        $count = 0;
        $units = [];

        foreach ($this->combinations($pack, $items) as $combination) {
            $eligible = $items->map(fn (PackItem $item, int $i) => $candidates[$i]
                ->filter(fn (MatchLine $line) => $this->rules($item)
                    ->every(fn (PackItemOption $rule) => $rule->mode !== OptionMode::Linked
                        || ! array_key_exists($rule->id, $combination)
                        || in_array($combination[$rule->id], $line->valueIds, true))));

            $n = $items->map(fn (PackItem $item, int $i) => intdiv(
                $eligible[$i]->sum(fn (MatchLine $line) => $remaining[$line->id]),
                $item->quantity,
            ))->min();

            if ($n <= 0) {
                continue;
            }

            foreach ($items as $i => $item) {
                $needed = $n * $item->quantity;

                foreach ($eligible[$i] as $line) {
                    $take = min($needed, $remaining[$line->id]);
                    $remaining[$line->id] -= $take;
                    $needed -= $take;

                    if ($take > 0) {
                        $units[$line->id] = ($units[$line->id] ?? 0) + $take;
                    }
                }
            }

            $count += $n;
        }

        return $count > 0 ? new PackMatch($pack, $count, $units) : null;
    }

    /**
     * The rules of an item that apply: on options the product still has.
     *
     * @return Collection<int, PackItemOption>
     */
    private function rules(PackItem $item): Collection
    {
        return $item->options->filter(fn (PackItemOption $rule) => $item->product->productOptions->contains($rule->product_option_id));
    }

    /**
     * Every choice of a row in each link: the cartesian product of the rows
     * of the links, each as column id (the rule) → value id. A link without
     * columns ties nothing and adds no choice; one without usable rows leaves
     * no choice at all. A row without a value for some column is dropped.
     *
     * @param  Collection<int, PackItem>  $items  the visible items
     * @return list<array<int, int>>
     */
    private function combinations(Pack $pack, Collection $items): array
    {
        $ruleIds = $items->flatMap(fn (PackItem $item) => $this->rules($item)->pluck('id'))->flip();

        $perLink = [];
        foreach ($pack->links as $link) {
            $columns = $link->columns->filter(fn (PackItemOption $column) => $ruleIds->has($column->id));

            if ($columns->isEmpty()) {
                continue;
            }

            $perLink[] = $link->rows
                ->map(function ($row) use ($columns) {
                    $values = $row->values->pluck('product_option_value_id', 'pack_item_option_id');

                    return $columns->mapWithKeys(fn (PackItemOption $column) => [$column->id => $values->get($column->id)])->all();
                })
                ->reject(fn (array $row) => in_array(null, $row, true))
                ->values()
                ->all();
        }

        if ($perLink === []) {
            return [[]];
        }

        return array_map(
            fn (array $rows) => array_replace(...$rows),
            Arr::crossJoin(...$perLink),
        );
    }
}
