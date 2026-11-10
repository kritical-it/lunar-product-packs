<?php

namespace KriticalIt\ProductPacks\Filament\Resources\PackResource\Pages;

use Illuminate\Validation\ValidationException;
use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Filament\Resources\PackResource;
use KriticalIt\ProductPacks\Models\Pack;

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
