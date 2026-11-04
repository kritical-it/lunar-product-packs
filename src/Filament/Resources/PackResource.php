<?php

namespace Gongarce\ProductPacks\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables;
use Filament\Tables\Table;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Filament\Resources\PackResource\Pages;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Gongarce\ProductPacks\PackValidator;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Support\Forms\Components\TranslatedText;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Product;

class PackResource extends BaseResource
{
    protected static ?string $model = Pack::class;

    protected static ?string $permission = 'catalog:manage-products';

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

    /** @var \WeakMap<Pack, list<string>>|null */
    private static ?\WeakMap $listed = null;

    /**
     * The validator's messages for a pack of a list, loaded with
     * listRelations(): as it is, once per record.
     *
     * @return list<string>
     */
    public static function listedErrors(Pack $pack): array
    {
        self::$listed ??= new \WeakMap;

        return self::$listed[$pack] ??= app(PackValidator::class)->errors($pack);
    }

    /**
     * What a list of packs loads to show their names and errors.
     *
     * @return list<string>
     */
    public static function listRelations(string $prefix = ''): array
    {
        return array_map(fn (string $relation) => $prefix.$relation, ['items.product', ...PackValidator::RELATIONS]);
    }

    /**
     * The validator's messages for the pack, as saved.
     *
     * @return list<string>
     */
    public static function errorMessages(Pack $pack): array
    {
        $fresh = $pack->fresh();

        return $fresh ? app(PackValidator::class)->errors($fresh) : [];
    }

    public static function getDefaultForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(fn (Pack $record) => $record->status === PackStatus::Published
                ? __('product-packs::panel.options.errors_published')
                : __('product-packs::panel.options.errors'))
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('warning')
                ->visible(fn (?Pack $record) => $record?->exists && self::errorMessages($record) !== [])
                ->schema([
                    Forms\Components\Placeholder::make('pack_errors')
                        ->hiddenLabel()
                        ->content(fn (Pack $record) => new HtmlString('<ul class="list-disc ps-5">'.collect(self::errorMessages($record))
                            ->map(fn (string $message) => '<li>'.e($message).'</li>')
                            ->join('').'</ul>')),
                ]),
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

    protected static function getDefaultTable(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(self::listRelations()))
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
                    ->state(fn (Pack $record): bool => self::listedErrors($record) !== [])
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                    ->tooltip(fn (Pack $record): ?string => implode(' ', self::listedErrors($record)) ?: null),
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

    protected static function getDefaultSubNavigation(): array
    {
        return [
            Pages\EditPack::class,
            Pages\ManagePackOptions::class,
        ];
    }

    protected static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListPacks::route('/'),
            'create' => Pages\CreatePack::route('/create'),
            'edit' => Pages\EditPack::route('/{record}/edit'),
            'options' => Pages\ManagePackOptions::route('/{record}/options'),
        ];
    }
}
