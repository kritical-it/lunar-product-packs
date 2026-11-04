<?php

namespace Gongarce\ProductPacks\Filament\Resources\ProductResource\Pages;

use Filament\Actions\Action;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables;
use Filament\Tables\Table;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Filament\Resources\PackResource;
use Gongarce\ProductPacks\Models\PackItem;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Support\Pages\BaseManageRelatedRecords;

/**
 * The packs the product is in. It only informs: creating and editing open
 * the pages of the pack.
 */
class ManageProductPacks extends BaseManageRelatedRecords
{
    protected static string $resource = ProductResource::class;

    protected static string $relationship = 'packItems';

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::product-packs');
    }

    public static function getNavigationLabel(): string
    {
        return __('product-packs::panel.plural_label');
    }

    public function getTitle(): string
    {
        return __('product-packs::panel.plural_label');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_pack')
                ->label(__('product-packs::panel.actions.create_with_product'))
                ->url(PackResource::getUrl('create', ['product' => $this->getRecord()->getKey()])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pack.items.product'))
            ->columns([
                Tables\Columns\TextColumn::make('pack_name')
                    ->label(__('product-packs::panel.fields.title'))
                    ->state(fn (PackItem $record): string => PackResource::packName($record->pack)),
                Tables\Columns\TextColumn::make('pack.status')
                    ->label(__('product-packs::panel.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PackStatus $state): string => PackResource::statusOptions()[$state->value])
                    ->color(fn (PackStatus $state): string => $state === PackStatus::Published ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('product-packs::panel.fields.quantity')),
                Tables\Columns\IconColumn::make('show_on_product')
                    ->label(__('product-packs::panel.fields.shown_here'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_errors')
                    ->label(__('product-packs::panel.fields.errors'))
                    ->state(fn (PackItem $record): bool => PackResource::errorMessages($record->pack) !== [])
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
            ])
            ->recordUrl(fn (PackItem $record): string => PackResource::getUrl('edit', ['record' => $record->pack]))
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label(__('filament-actions::edit.single.label'))
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (PackItem $record): string => PackResource::getUrl('edit', ['record' => $record->pack])),
            ]);
    }
}
