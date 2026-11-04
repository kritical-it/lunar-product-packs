<?php

namespace Gongarce\ProductPacks\Tests;

use Gongarce\ProductPacks\ProductPacksPlugin;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;

class TestPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(fn ($panel) => $panel->plugin(new ProductPacksPlugin))->register();
    }
}
