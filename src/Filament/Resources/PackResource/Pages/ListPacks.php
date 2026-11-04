<?php

namespace Gongarce\ProductPacks\Filament\Resources\PackResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Gongarce\ProductPacks\Filament\Resources\PackResource;

class ListPacks extends ListRecords
{
    protected static string $resource = PackResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
