<?php

namespace Gongarce\ProductPacks\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables;
use Filament\Tables\Table;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Filament\Resources\PackResource\Pages;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Gongarce\ProductPacks\PackValidator;
use Lunar\Admin\Support\Forms\Components\TranslatedText;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Product;

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

    /**
     * The title of the pack or, without one, its products.
     */
    public static function packName(Pack $pack): string
    {
        $title = $pack->translate('title');

        if (filled($title)) {
            return $title;
        }

        return $pack->items
            ->map(fn (PackItem $item) => $item->product?->translateAttribute('name'))
            ->filter()
            ->join(' + ') ?: __('product-packs::panel.untitled');
    }

    /**
     * The validator's messages for the pack.
     *
     * @return list<string>
     */
    public static function errorMessages(Pack $pack): array
    {
        return array_values(array_unique(array_map(
            fn (string $key) => __('product-packs::validation.'.$key),
            app(PackValidator::class)->errors($pack->fresh()),
        )));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                TranslatedText::make('title')
                    ->label(__('product-packs::panel.fields.title'))
                    ->helperText(__('product-packs::panel.fields.title_help')),
                Forms\Components\ToggleButtons::make('status')
                    ->label(__('product-packs::panel.fields.status'))
                    ->options(self::statusOptions())
                    ->colors([PackStatus::Published->value => 'success', PackStatus::Draft->value => 'gray'])
                    ->default(PackStatus::Draft->value)
                    ->inline()
                    ->required(),
            ]),
            Forms\Components\Repeater::make('items')
                ->label(__('product-packs::panel.fields.items'))
                ->relationship('items')
                ->orderColumn('position')
                ->defaultItems(0)
                ->columns(3)
                ->addActionLabel(__('product-packs::panel.actions.add_item'))
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label(__('product-packs::panel.fields.product'))
                        ->searchable()
                        ->getSearchResultsUsing(static fn (string $search): array => get_search_builder(Product::modelClass(), $search)
                            ->get()
                            ->mapWithKeys(fn (ProductContract $product): array => [$product->getKey() => $product->translateAttribute('name')])
                            ->all())
                        ->getOptionLabelUsing(static fn ($value): ?string => Product::modelClass()::find($value)?->translateAttribute('name'))
                        // A saved product isn't changed: removed and added
                        // again, so no rule is left with the options of
                        // another product.
                        ->disabled(static fn (?PackItem $record): bool => $record !== null)
                        ->distinct()
                        ->required(),
                    Forms\Components\TextInput::make('quantity')
                        ->label(__('product-packs::panel.fields.quantity'))
                        ->integer()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    Forms\Components\Toggle::make('show_on_product')
                        ->label(__('product-packs::panel.fields.show_on_product'))
                        ->default(true)
                        ->inline(false),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('items.product'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('product-packs::panel.fields.title'))
                    ->state(fn (Pack $record): string => self::packName($record)),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('product-packs::panel.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PackStatus $state): string => self::statusOptions()[$state->value])
                    ->color(fn (PackStatus $state): string => $state === PackStatus::Published ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('product-packs::panel.fields.items'))
                    ->counts('items'),
                Tables\Columns\IconColumn::make('has_errors')
                    ->label(__('product-packs::panel.fields.errors'))
                    ->state(fn (Pack $record): bool => self::errorMessages($record) !== [])
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                    ->tooltip(fn (Pack $record): ?string => implode(' ', self::errorMessages($record)) ?: null),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('product-packs::panel.fields.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('product-packs::panel.fields.status'))
                    ->options(self::statusOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            PackStatus::Published->value => __('product-packs::panel.status.published'),
            PackStatus::Draft->value => __('product-packs::panel.status.draft'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\EditPack::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPacks::route('/'),
            'create' => Pages\CreatePack::route('/create'),
            'edit' => Pages\EditPack::route('/{record}/edit'),
        ];
    }
}
