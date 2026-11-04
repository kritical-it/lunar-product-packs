<?php

namespace Gongarce\ProductPacks\Filament\Resources\PackResource\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Gongarce\ProductPacks\Editing\PackEditor;
use Gongarce\ProductPacks\Enums\OptionMode;
use Gongarce\ProductPacks\Enums\PackStatus;
use Gongarce\ProductPacks\Filament\Resources\PackResource;
use Gongarce\ProductPacks\Models\Pack;
use Gongarce\ProductPacks\Models\PackItem;
use Gongarce\ProductPacks\Models\PackItemOption;
use Gongarce\ProductPacks\Models\PackLink;
use Gongarce\ProductPacks\Models\PackLinkRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;

/**
 * The rule of each option and the rows of each link. The columns of a link
 * come from the saved rules, so the rows are edited on what is saved.
 *
 * @property Form $rulesForm
 * @property Form $linksForm
 */
class ManagePackOptions extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = PackResource::class;

    protected static string $view = 'product-packs::pages.manage-pack-options';

    /** ['item_{id}' => ['option_{id}' => ['choice' => …, 'value' => …]]] */
    public ?array $rules = [];

    /** ['link_{id}' => ['rows' => [key => ['rule_{id}' => valueId]]]] */
    public ?array $links = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->fillForms();
    }

    public static function getNavigationLabel(): string
    {
        return __('product-packs::panel.pages.options');
    }

    public function getTitle(): string
    {
        return __('product-packs::panel.pages.options');
    }

    public function getSubheading(): ?string
    {
        return PackResource::packName($this->pack());
    }

    protected function getForms(): array
    {
        return ['rulesForm', 'linksForm'];
    }

    public function rulesForm(Form $form): Form
    {
        $letters = $this->letters();

        return $form
            ->statePath('rules')
            ->schema($this->pack()->items->map(fn (PackItem $item) => Forms\Components\Section::make($this->itemLabel($item))
                ->statePath("item_{$item->id}")
                ->columns(2)
                ->schema(! $item->product || $item->product->productOptions->isEmpty()
                    ? [Forms\Components\Placeholder::make('none')->hiddenLabel()->content($item->product
                        ? __('product-packs::panel.options.no_options')
                        : __('product-packs::panel.options.deleted_product'))]
                    : $item->product->productOptions->map(fn (ProductOption $option) => Forms\Components\Group::make()
                        ->statePath("option_{$option->id}")
                        ->columns(2)
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Select::make('choice')
                                ->label($option->translate('name'))
                                ->options([
                                    'free' => __('product-packs::panel.options.free'),
                                    'fixed' => __('product-packs::panel.options.fixed'),
                                    ...collect($letters)->mapWithKeys(fn (string $letter) => ["link:{$letter}" => __('product-packs::panel.options.link', ['letter' => $letter])])->all(),
                                ])
                                ->default('free')
                                ->selectablePlaceholder(false)
                                ->live(),
                            Forms\Components\Select::make('value')
                                ->label(__('product-packs::panel.options.value'))
                                ->options($option->values->mapWithKeys(fn (ProductOptionValue $value) => [$value->id => $value->translate('name')])->all())
                                ->visible(fn (Get $get) => $get('choice') === 'fixed')
                                ->required(),
                        ]))->all())
            )->all());
    }

    public function linksForm(Form $form): Form
    {
        $editor = app(PackEditor::class);

        return $form
            ->statePath('links')
            ->schema($this->pack()->links->values()->map(function (PackLink $link, int $i) use ($editor) {
                $columns = $editor->columns($link);

                return Forms\Components\Section::make(__('product-packs::panel.options.link', ['letter' => $this->letter($i)]))
                    ->description($columns->map(fn (PackItemOption $rule) => $this->columnLabel($rule))->join(' ↔ '))
                    ->statePath("link_{$link->id}")
                    ->key("link_{$link->id}")
                    ->headerActions([
                        Forms\Components\Actions\Action::make("propose_{$link->id}")
                            ->label(__('product-packs::panel.options.propose'))
                            ->action(fn () => $this->proposeRows($link->id)),
                    ])
                    ->schema([
                        Forms\Components\Repeater::make('rows')
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->reorderable()
                            ->addActionLabel(__('product-packs::panel.options.add_row'))
                            ->columns(max(1, $columns->count()))
                            ->schema($columns->map(fn (PackItemOption $rule) => Forms\Components\Select::make("rule_{$rule->id}")
                                ->label($this->columnLabel($rule))
                                ->options($rule->option->values->mapWithKeys(fn (ProductOptionValue $value) => [$value->id => $value->translate('name')])->all())
                                ->required())->all()),
                    ]);
            })->all());
    }

    public function saveRules(): void
    {
        if ($this->isStale()) {
            return;
        }

        $state = $this->rulesForm->getState();
        $editable = $this->pack()->items->filter(fn (PackItem $item) => $item->product)->pluck('id')->all();
        $choices = [];

        foreach ($state as $itemKey => $options) {
            $itemId = (int) Str::after($itemKey, 'item_');

            // A deleted product shows no options: its rules are kept.
            if (! in_array($itemId, $editable, true)) {
                continue;
            }

            $choices[$itemId] = [];

            foreach ((array) $options as $optionKey => $choice) {
                if (! is_array($choice) || ! Str::startsWith($optionKey, 'option_')) {
                    continue;
                }

                $choices[$itemId][(int) Str::after($optionKey, 'option_')] = [
                    'choice' => $choice['choice'] ?? 'free',
                    'value' => isset($choice['value']) ? (int) $choice['value'] : null,
                ];
            }
        }

        app(PackEditor::class)->saveRules($this->pack(), $choices);

        // The links depend on the rules: both forms are rebuilt.
        $this->fillForms();
        $this->notifySaved();
    }

    public function saveLinks(): void
    {
        if ($this->isStale()) {
            return;
        }

        $state = $this->linksForm->getState();
        $editor = app(PackEditor::class);
        $toSave = [];

        foreach ($this->pack()->links as $link) {
            $rows = collect($state["link_{$link->id}"]['rows'] ?? [])
                ->map(fn (array $row) => collect($row)
                    ->mapWithKeys(fn ($valueId, $key) => [(int) Str::after($key, 'rule_') => (int) $valueId])
                    ->sortKeys()
                    ->all())
                ->values();

            if ($rows->map(fn (array $row) => json_encode($row))->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    "links.link_{$link->id}.rows" => __('product-packs::validation.duplicate_row'),
                ]);
            }

            $toSave[] = [$link, $rows->all()];
        }

        DB::transaction(function () use ($editor, $toSave) {
            foreach ($toSave as [$link, $rows]) {
                $editor->saveRows($link, $rows);
            }
        });

        // Only the links: unsaved changes to the options stay.
        $this->fillForms(rules: false);
        $this->notifySaved();
    }

    /**
     * Adds to the form the rows of the link whose values are called the
     * same. Nothing is saved: they are reviewed first.
     */
    public function proposeRows(int $linkId): void
    {
        $link = $this->pack()->links->firstWhere('id', $linkId);

        if (! $link) {
            return;
        }

        $key = "link_{$link->id}";
        $current = collect($this->links[$key]['rows'] ?? [])
            ->map(fn (array $row) => collect($row)
                ->filter(fn ($valueId) => filled($valueId))
                ->mapWithKeys(fn ($valueId, $ruleKey) => [(int) Str::after($ruleKey, 'rule_') => (int) $valueId])
                ->all())
            ->values()
            ->all();

        $proposed = app(PackEditor::class)->proposeRows($link, $current);

        foreach ($proposed as $row) {
            $this->links[$key]['rows'][(string) Str::uuid()] = collect($row)
                ->mapWithKeys(fn (int $valueId, int $ruleId) => ["rule_{$ruleId}" => $valueId])
                ->all();
        }

        Notification::make()
            ->title($proposed === []
                ? __('product-packs::panel.options.proposed_none')
                : trans_choice('product-packs::panel.options.proposed', count($proposed), ['count' => count($proposed)]))
            ->{$proposed === [] ? 'warning' : 'success'}()
            ->send();
    }

    /**
     * @return list<string>
     */
    public function getPackErrors(): array
    {
        return PackResource::errorMessages($this->pack());
    }

    public function isPublished(): bool
    {
        return $this->pack()->status === PackStatus::Published;
    }

    public function hasLinks(): bool
    {
        return $this->pack()->links->isNotEmpty();
    }

    /**
     * What the forms were built for: the products, their options and the
     * links. Saving from a page opened before the pack changed would apply
     * its letters and fields to another pack.
     */
    public string $fingerprint = '';

    private function fingerprint(Pack $pack): string
    {
        return md5(json_encode([
            $pack->items->map(fn (PackItem $item) => [$item->id, $item->product?->id, $item->product?->productOptions->pluck('id')->all()])->all(),
            $pack->links->pluck('id')->all(),
        ]));
    }

    private function isStale(): bool
    {
        if ($this->fingerprint === $this->fingerprint($this->pack(fresh: true))) {
            return false;
        }

        Notification::make()
            ->title(__('product-packs::panel.options.stale'))
            ->danger()
            ->persistent()
            ->send();

        return true;
    }

    private function fillForms(bool $rules = true): void
    {
        $pack = $this->pack(fresh: true);
        $this->fingerprint = $this->fingerprint($pack);
        $letters = $pack->links->values()->mapWithKeys(fn (PackLink $link, int $i) => [$link->id => $this->letter($i)]);

        $rulesState = [];
        foreach ($pack->items as $item) {
            foreach ($item->product?->productOptions ?? [] as $option) {
                $rule = $item->options->firstWhere('product_option_id', $option->id);

                $rulesState["item_{$item->id}"]["option_{$option->id}"] = match (true) {
                    $rule?->mode === OptionMode::Fixed => ['choice' => 'fixed', 'value' => $rule->product_option_value_id],
                    $rule?->mode === OptionMode::Linked && $letters->has($rule->pack_link_id) => ['choice' => 'link:'.$letters[$rule->pack_link_id], 'value' => null],
                    default => ['choice' => 'free', 'value' => null],
                };
            }
        }

        $editor = app(PackEditor::class);
        $links = [];
        foreach ($pack->links as $link) {
            $columns = $editor->columns($link);
            $links["link_{$link->id}"]['rows'] = $link->rows
                ->mapWithKeys(fn (PackLinkRow $row) => [(string) Str::uuid() => $columns
                    ->mapWithKeys(fn (PackItemOption $rule) => ["rule_{$rule->id}" => $row->values->firstWhere('pack_item_option_id', $rule->id)?->product_option_value_id])
                    ->all()])
                ->all();
        }

        // Forms are rebuilt from the saved pack before they are filled.
        $this->cacheForms();
        if ($rules) {
            $this->rulesForm->fill($rulesState);
        }
        $this->linksForm->fill($links);
    }

    private ?Pack $loaded = null;

    private function pack(bool $fresh = false): Pack
    {
        if ($fresh || ! $this->loaded) {
            $this->loaded = $this->getRecord()->fresh()->load([
                'items.product.productOptions.values',
                'items.options',
                'links.rows.values',
            ]);
        }

        return $this->loaded;
    }

    /**
     * The letters of the links of the pack, and one more for a new link.
     *
     * @return list<string>
     */
    private function letters(): array
    {
        return array_map(fn (int $i) => $this->letter($i), range(0, $this->pack()->links->count()));
    }

    private function letter(int $index): string
    {
        return chr(ord('A') + $index);
    }

    private function itemLabel(PackItem $item): string
    {
        return ($item->product?->translateAttribute('name') ?? __('product-packs::panel.options.deleted_product')).' · × '.$item->quantity;
    }

    private function columnLabel(PackItemOption $rule): string
    {
        return ($rule->item->product?->translateAttribute('name') ?? __('product-packs::panel.options.deleted_product')).': '.$rule->option->translate('name');
    }

    private function notifySaved(): void
    {
        Notification::make()->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))->success()->send();
    }
}
