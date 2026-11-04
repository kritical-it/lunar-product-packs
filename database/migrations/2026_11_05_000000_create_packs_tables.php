<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

/**
 * Every index and foreign key has a short explicit name: MySQL allows 64
 * characters, and the default names with the table prefix go beyond them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $p = $this->prefix;

        Schema::create($p.'packs', function (Blueprint $table) use ($p) {
            $table->id();
            $table->json('title')->nullable();
            $table->string('status')->default('draft')->index($p.'packs_status_idx');
            $table->timestamps();
        });

        Schema::create($p.'pack_items', function (Blueprint $table) use ($p) {
            $table->id();
            $table->foreignId('pack_id')->constrained($p.'packs', indexName: $p.'pack_items_pack_fk')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained($p.'products', indexName: $p.'pack_items_product_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('show_on_product')->default(true);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['pack_id', 'product_id'], $p.'pack_items_unique');
        });

        Schema::create($p.'pack_links', function (Blueprint $table) use ($p) {
            $table->id();
            $table->foreignId('pack_id')->constrained($p.'packs', indexName: $p.'pack_links_pack_fk')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create($p.'pack_item_options', function (Blueprint $table) use ($p) {
            $table->id();
            $table->foreignId('pack_item_id')->constrained($p.'pack_items', indexName: $p.'pack_rules_item_fk')->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained($p.'product_options', indexName: $p.'pack_rules_option_fk')->cascadeOnDelete();
            $table->string('mode');
            // The value of a fixed option. Deleting it leaves the rule without
            // value, so the pack stops showing (and the validator reports it)
            // instead of offering the option freely.
            $table->foreignId('product_option_value_id')->nullable()->constrained($p.'product_option_values', indexName: $p.'pack_rules_value_fk')->nullOnDelete();
            $table->foreignId('pack_link_id')->nullable()->constrained($p.'pack_links', indexName: $p.'pack_rules_link_fk')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pack_item_id', 'product_option_id'], $p.'pack_rules_unique');
        });

        Schema::create($p.'pack_link_rows', function (Blueprint $table) use ($p) {
            $table->id();
            $table->foreignId('pack_link_id')->constrained($p.'pack_links', indexName: $p.'pack_rows_link_fk')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create($p.'pack_link_row_values', function (Blueprint $table) use ($p) {
            $table->id();
            $table->foreignId('pack_link_row_id')->constrained($p.'pack_link_rows', indexName: $p.'pack_values_row_fk')->cascadeOnDelete();
            $table->foreignId('pack_item_option_id')->constrained($p.'pack_item_options', indexName: $p.'pack_values_rule_fk')->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained($p.'product_option_values', indexName: $p.'pack_values_value_fk')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pack_link_row_id', 'pack_item_option_id'], $p.'pack_values_unique');
        });
    }

    public function down(): void
    {
        foreach (['pack_link_row_values', 'pack_link_rows', 'pack_item_options', 'pack_links', 'pack_items', 'packs'] as $table) {
            Schema::dropIfExists($this->prefix.$table);
        }
    }
};
