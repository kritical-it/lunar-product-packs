<?php

use Illuminate\Support\Facades\Schema;

/**
 * MySQL allows names of 64 characters. The names of the plugin are short
 * and explicit, so a long table prefix still fits.
 */
it('names every index of its tables within 64 characters, also with a long prefix', function () {
    foreach (['packs', 'pack_items', 'pack_links', 'pack_item_options', 'pack_link_rows', 'pack_link_row_values'] as $table) {
        foreach (Schema::getIndexes($this->tablePrefix().$table) as $index) {
            expect(strlen($index['name']))->toBeLessThanOrEqual(64, "{$index['name']} on {$table}");
        }
    }
});
