<?php

namespace Gongarce\ProductPacks\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Support\Facades\FilamentIcon;
use Gongarce\ProductPacks\Filament\Resources\PackResource\Pages;
use Gongarce\ProductPacks\Models\Pack;

class PackResource extends Resource
{
    protected static ?string $model = Pack::class;

    protected static ?int $navigationSort = 4;

    public static function getModel(): string
    {
        return Pack::modelClass();
    }

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::product-packs');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }

    public static function getModelLabel(): string
    {
        return __('product-packs::panel.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('product-packs::panel.plural_label');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPacks::route('/'),
        ];
    }
}
