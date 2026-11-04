<?php

namespace Gongarce\ProductPacks\Enums;

/**
 * How a pack sets an option of one of its products. An option with no rule is
 * free: the customer chooses it.
 */
enum OptionMode: string
{
    /** Always the same value. */
    case Fixed = 'fixed';

    /** Paired with options of other products of the pack, row by row (PackLink). */
    case Linked = 'linked';
}
