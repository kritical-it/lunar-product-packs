<?php

namespace KriticalIt\ProductPacks\Tests;

use Illuminate\Support\ServiceProvider;
use KriticalIt\ProductPacks\ProductPacksPlugin;
use Lunar\Admin\Support\Facades\LunarPanel;

class TestPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(fn ($panel) => $panel->plugin(new ProductPacksPlugin))->register();
    }
}
