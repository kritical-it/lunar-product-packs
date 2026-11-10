<?php

namespace KriticalIt\ProductPacks\Filament\Resources\PackResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use KriticalIt\ProductPacks\Editing\PackEditor;
use KriticalIt\ProductPacks\Filament\Resources\PackResource;

class CreatePack extends CreateRecord
{
    use GuardsPublishing;

    protected static string $resource = PackResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (ValidationException $exception) {
            // The transaction is rolled back: the record no longer exists.
            $this->record = null;

            throw $exception;
        }
    }

    protected function afterFill(): void
    {
        // From the tab of a product: that product first.
        $product = request()->integer('product');

        if ($product > 0) {
            $this->data['items'] = [
                (string) Str::uuid() => ['product_id' => $product, 'quantity' => 1, 'show_on_product' => true],
            ];
        }
    }

    protected function afterCreate(): void
    {
        app(PackEditor::class)->tidy($this->getRecord());
        $this->ensurePublishable($this->getRecord(), null);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
