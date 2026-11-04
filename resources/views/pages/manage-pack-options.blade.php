<x-filament-panels::page>
    @if ($packErrors = $this->getPackErrors())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">
                {{ $this->isPublished() ? __('product-packs::panel.options.errors_published') : __('product-packs::panel.options.errors') }}
            </x-slot>
            <ul class="list-disc ps-5 text-sm">
                @foreach ($packErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament-panels::form wire:submit="saveRules">
        {{ $this->rulesForm }}

        <div>
            <x-filament::button type="submit">
                {{ __('product-packs::panel.options.save_rules') }}
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    @if ($this->hasLinks())
        <x-filament-panels::form wire:submit="saveLinks">
            {{ $this->linksForm }}

            <div>
                <x-filament::button type="submit">
                    {{ __('product-packs::panel.options.save_links') }}
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    @endif
</x-filament-panels::page>
