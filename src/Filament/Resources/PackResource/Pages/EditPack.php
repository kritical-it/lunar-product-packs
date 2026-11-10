<?php

namespace KriticalIt\ProductPacks\Filament\Resources\PackResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use KriticalIt\ProductPacks\Editing\PackEditor;
use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Filament\Resources\PackResource;

class EditPack extends EditRecord
{
    use GuardsPublishing;

    protected static string $resource = PackResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected ?PackStatus $statusBefore = null;

    public static function getNavigationLabel(): string
    {
        return __('product-packs::panel.pages.edit');
    }

    public function getTitle(): string
    {
        return PackResource::packName($this->getRecord());
    }

    protected function beforeSave(): void
    {
        $this->statusBefore = $this->getRecord()->getOriginal('status');
    }

    protected function afterSave(): void
    {
        app(PackEditor::class)->tidy($this->getRecord());
        $this->ensurePublishable($this->getRecord(), $this->statusBefore);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
