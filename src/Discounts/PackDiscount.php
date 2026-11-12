<?php

namespace KriticalIt\ProductPacks\Discounts;

use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Collection;
use Lunar\Admin\Base\LunarPanelDiscountInterface;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdown;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdownLine;
use Lunar\DataTypes\Price;
use Lunar\DiscountTypes\AbstractDiscountType;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;

/**
 * The % of each pack, on the units of the cart that make complete packs.
 *
 * Its record has priority 0, so it runs after every other discount (a
 * coupon too), and it always adds its amount to what the line already has
 * off: a coupon of 5 % and a pack of 7 % make 12 %. The amount comes from
 * the price of the line, not from what is left after the other discounts.
 */
class PackDiscount extends AbstractDiscountType implements LunarPanelDiscountInterface
{
    public const HANDLE = 'product-packs';

    public function getName(): string
    {
        return __('product-packs::panel.discount.name');
    }

    public function apply(CartContract $cart): CartContract
    {
        /** @var Cart $cart */
        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        // A second record of this type would discount the packs twice.
        if ($cart->discountBreakdown?->contains(fn (DiscountBreakdown $breakdown) => is_a($breakdown->discount->type, self::class, true))) {
            return $cart;
        }

        $discounts = app(CartPackDiscounts::class);
        $lines = $cart->lines->keyBy('id');
        $applied = false;

        foreach ($discounts->for($cart) as $match) {
            $percentage = (float) $match->pack->discount_percentage;
            $total = 0;
            $affected = collect();

            foreach ($match->units as $lineId => $units) {
                $line = $lines[$lineId];
                $amount = $discounts->amount($line, $units, $percentage);
                $lineDiscount = ($line->discountTotal?->value ?? 0) + $amount;

                $line->discountTotal = new Price($lineDiscount, $cart->currency, 1);
                $line->subTotalDiscounted = new Price(max(0, $line->subTotal->value - $lineDiscount), $cart->currency, 1);

                $affected->push(new DiscountBreakdownLine(line: $line, quantity: $units));
                $total += $amount;
            }

            if ($total <= 0) {
                continue;
            }

            $this->addDiscountBreakdown($cart, new DiscountBreakdown(
                price: new Price($total, $cart->currency, 1),
                lines: $affected,
                discount: $this->discount,
            ));
            $applied = true;
        }

        if ($applied) {
            $cart->discounts ??= collect();
            $cart->discounts->push($this);
        }

        return $cart;
    }

    /**
     * Attach the discount, enabled and visible from now, to the channels and
     * customer groups it does not have yet; those it has stay as they are.
     *
     * @param  Collection<int, Channel>|null  $channels  all when null
     * @param  Collection<int, CustomerGroup>|null  $groups  all when null
     */
    public static function makeAvailable(Discount $discount, ?Collection $channels = null, ?Collection $groups = null, bool $replace = false): void
    {
        $channels ??= Channel::modelClass()::get();
        $groups ??= CustomerGroup::modelClass()::get();

        $attached = $replace ? collect() : $discount->channels()->allRelatedIds();
        $discount->channels()->syncWithoutDetaching($channels
            ->reject(fn ($channel) => $attached->contains($channel->id))
            ->mapWithKeys(fn ($channel) => [$channel->id => ['enabled' => true, 'starts_at' => now(), 'ends_at' => null]])
            ->all());

        $attached = $replace ? collect() : $discount->customerGroups()->allRelatedIds();
        $discount->customerGroups()->syncWithoutDetaching($groups
            ->reject(fn ($group) => $attached->contains($group->id))
            ->mapWithKeys(fn ($group) => [$group->id => ['enabled' => true, 'visible' => true, 'starts_at' => now(), 'ends_at' => null]])
            ->all());
    }

    /**
     * The records of this type.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Discount>
     */
    public static function records(): \Illuminate\Database\Eloquent\Collection
    {
        return Discount::modelClass()::query()->where('type', static::class)->get();
    }

    public function lunarPanelSchema(): array
    {
        return [
            Placeholder::make('product_packs_help')
                ->hiddenLabel()
                ->content(__('product-packs::panel.discount.help')),
        ];
    }

    public function lunarPanelOnFill(array $data): array
    {
        return $data;
    }

    public function lunarPanelOnSave(array $data): array
    {
        return $data;
    }

    public function lunarPanelRelationManagers(): array
    {
        return [];
    }
}
