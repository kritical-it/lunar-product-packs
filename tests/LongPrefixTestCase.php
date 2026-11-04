<?php

namespace Gongarce\ProductPacks\Tests;

/**
 * The tables with a long prefix: 30 characters.
 */
abstract class LongPrefixTestCase extends TestCase
{
    protected function tablePrefix(): string
    {
        return 'a_rather_long_table_prefix_30_';
    }
}
