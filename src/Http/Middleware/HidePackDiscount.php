<?php

namespace KriticalIt\ProductPacks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use KriticalIt\ProductPacks\Discounts\PackDiscount;
use Lunar\Models\Discount;
use Symfony\Component\HttpFoundation\Response;

/**
 * No page of the panel opens the «Packs» discount, not even by its URL
 * (the edit page and its availability and limitations).
 */
class HidePackDiscount
{
    public function handle(Request $request, Closure $next): Response
    {
        $record = $request->route('record');

        if ($record !== null
            && $request->routeIs('filament.*.resources.discounts.*')
            && Discount::modelClass()::query()->whereKey($record)->where('type', PackDiscount::class)->exists()) {
            abort(404);
        }

        return $next($request);
    }
}
