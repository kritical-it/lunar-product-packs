<?php

namespace KriticalIt\ProductPacks\Tests;

/**
 * The plugin turned off from the start, as LUNAR_PRODUCT_PACKS_ENABLED=false.
 */
abstract class DisabledPluginTestCase extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('lunar.product-packs.enabled', false);
    }
}
