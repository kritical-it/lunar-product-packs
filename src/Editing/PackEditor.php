<?php

namespace Gongarce\ProductPacks\Editing;

use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Gongarce\ProductPacks\Models\PackItemOption;
use Gongarce\ProductPacks\Models\PackLink;
use Gongarce\ProductPacks\Models\PackLinkRow;
use Gongarce\ProductPacks\Models\PackLinkRowValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\ProductOptionValue;

/**
 * The edits of the panel that aren't screens: the rule of each option, the
 * rows of each link, tidying up after a product leaves, and the rows
 * proposed by name. Each save is one transaction, so one PackChanged.
 */
class PackEditor
{
    /**
     * The rule of each option of each product of the pack.
     *
     * @param  array<int, array<int, array{choice: string, value?: ?int}>>  $choices
     *                                                                                [itemId => [optionId => ['choice' => 'free'|'fixed'|'link:A'…, 'value' => ?int]]]
     *                                                                                The options not given lose their rule: their product no longer has them.
     */
    public function saveRules(Pack $pack, array $choices): void
    {
        DB::transaction(function () use ($pack, $choices) {
            $existingLinks = $pack->links()->get()->values();
            $links = [];
            $linkFor = function (string $letter) use ($pack, $existingLinks, &$links): PackLink {
                $index = ord(strtoupper($letter)) - ord('A');

                return $links[$letter] ??= $existingLinks->get($index)
                    ?? $pack->links()->create(['position' => $existingLinks->count() + count($links) + 1]);
            };

            /** @var PackItem $item */
            foreach ($pack->items()->with('options')->get() as $item) {
                $given = $choices[$item->id] ?? [];

                foreach ($item->options as $rule) {
                    if (! array_key_exists($rule->product_option_id, $given)) {
                        $rule->delete();
                    }
                }

                foreach ($given as $optionId => $choice) {
                    $rule = $item->options->firstWhere('product_option_id', (int) $optionId);
                    $kind = $choice['choice'] ?? 'free';

                    if ($kind === 'free') {
                        $rule?->delete();

                        continue;
                    }

                    $attributes = $kind === 'fixed'
                        ? ['mode' => OptionMode::Fixed, 'product_option_value_id' => $choice['value'] ?? null, 'pack_link_id' => null]
                        : ['mode' => OptionMode::Linked, 'product_option_value_id' => null, 'pack_link_id' => $linkFor(substr($kind, strlen('link:')))->id];

                    if ($rule) {
                        $rule->update($attributes);
                    } else {
                        $item->options()->create(['product_option_id' => (int) $optionId, ...$attributes]);
                    }
                }
            }

            $this->tidy($pack);
        });
    }

    /**
     * Replaces the rows of the link, in this order.
     *
     * @param  list<array<int, int>>  $rows  [ruleId => valueId] each
     */
    public function saveRows(PackLink $link, array $rows): void
    {
        DB::transaction(function () use ($link, $rows) {
            $link->rows()->get()->each->delete();

            foreach (array_values($rows) as $i => $row) {
                $created = $link->rows()->create(['position' => $i + 1]);

                foreach ($row as $ruleId => $valueId) {
                    $created->values()->create(['pack_item_option_id' => $ruleId, 'product_option_value_id' => $valueId]);
                }
            }
        });
    }

    /**
     * Leaves the links of the pack consistent with its rules: rows only
     * with values of their link's columns and without repeats, no link
     * without columns, positions from one.
     */
    public function tidy(Pack $pack): void
    {
        DB::transaction(function () use ($pack) {
            $position = 0;

            foreach ($pack->links()->with(['columns', 'rows.values'])->get() as $link) {
                $columns = $link->columns->filter(fn (PackItemOption $rule) => $rule->mode === OptionMode::Linked);

                if ($columns->isEmpty()) {
                    $link->delete();

                    continue;
                }

                $ruleIds = $columns->pluck('id')->all();
                $seen = [];
                $rowPosition = 0;

                foreach ($link->rows as $row) {
                    $row->values
                        ->reject(fn (PackLinkRowValue $value) => in_array($value->pack_item_option_id, $ruleIds))
                        ->each->delete();

                    $values = $row->values()->pluck('product_option_value_id', 'pack_item_option_id')->sortKeys()->all();
                    $signature = json_encode($values);

                    if ($values === [] || isset($seen[$signature])) {
                        $row->delete();

                        continue;
                    }

                    $seen[$signature] = true;
                    $this->renumber($row, ++$rowPosition);
                }

                $this->renumber($link, ++$position);
            }
        });
    }

    /**
     * The rows missing from $rows whose values are called the same in every
     * column of the link: in the default language, ignoring case and extra
     * spaces. Nothing is saved.
     *
     * @param  list<array<int, int>>  $rows  [ruleId => valueId] each
     * @return list<array<int, int>>
     */
    public function proposeRows(PackLink $link, array $rows): array
    {
        $columns = $this->columns($link);

        if ($columns->count() < 2) {
            return [];
        }

        $seen = collect($rows)->map(fn (array $row) => $this->signature($row))->flip();
        $byName = $columns->map(fn (PackItemOption $rule) => $rule->option->values
            ->keyBy(fn (ProductOptionValue $value) => self::normalize($value->translate('name'))));
        $proposed = [];

        foreach ($byName->first() as $name => $value) {
            $row = [];

            foreach ($columns as $i => $rule) {
                $match = $byName[$i]->get($name);

                if (! $match) {
                    continue 2;
                }

                $row[$rule->id] = $match->id;
            }

            $signature = $this->signature($row);

            if (! $seen->has($signature)) {
                $seen[$signature] = true;
                $proposed[] = $row;
            }
        }

        return $proposed;
    }

    /**
     * The columns of a link in the order of their products in the pack.
     *
     * @return Collection<int, PackItemOption>
     */
    public function columns(PackLink $link): Collection
    {
        return $link->columns()
            ->where('mode', OptionMode::Linked)
            ->with(['item.product', 'option.values'])
            ->get()
            ->sortBy(fn (PackItemOption $rule) => [$rule->item->position, $rule->item->id, $rule->id])
            ->values();
    }

    public static function normalize(?string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));
    }

    private function signature(array $row): string
    {
        ksort($row);

        return json_encode(array_map('intval', $row));
    }

    private function renumber(PackLink|PackLinkRow $model, int $position): void
    {
        if ($model->position !== $position) {
            $model->update(['position' => $position]);
        }
    }
}
