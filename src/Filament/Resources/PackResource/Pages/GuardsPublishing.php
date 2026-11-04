<?php

namespace Gongarce\ProductPacks\Filament\Resources\PackResource\Pages;

use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Filament\Resources\PackResource;
use Gongarce\ProductPacks\Models\Pack;
use Illuminate\Validation\ValidationException;

/**
 * Publishing needs a pack without errors. Thrown inside the page's
 * transaction, nothing is saved. A pack already published that is left
 * with errors some other way is saved, and its pages say so.
 */
trait GuardsPublishing
{
    protected function ensurePublishable(Pack $pack, ?PackStatus $before): void
    {
        if ($pack->status !== PackStatus::Published || $before === PackStatus::Published) {
            return;
        }

        $messages = PackResource::errorMessages($pack);

        if ($messages !== []) {
            throw ValidationException::withMessages([
                'data.status' => [__('product-packs::panel.cannot_publish'), ...$messages],
            ]);
        }
    }
}
