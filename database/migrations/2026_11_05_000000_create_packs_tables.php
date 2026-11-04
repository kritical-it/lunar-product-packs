<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->prefix.'packs', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create($this->prefix.'pack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained($this->prefix.'packs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained($this->prefix.'products')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('show_on_product')->default(true);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['pack_id', 'product_id']);
        });

        Schema::create($this->prefix.'pack_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained($this->prefix.'packs')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create($this->prefix.'pack_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_item_id')->constrained($this->prefix.'pack_items')->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained($this->prefix.'product_options')->cascadeOnDelete();
            $table->string('mode');
            // The value of a fixed option: deleting it deletes the rule.
            $table->foreignId('product_option_value_id')->nullable()->constrained($this->prefix.'product_option_values')->cascadeOnDelete();
            $table->foreignId('pack_link_id')->nullable()->constrained($this->prefix.'pack_links')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pack_item_id', 'product_option_id']);
        });

        Schema::create($this->prefix.'pack_link_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_link_id')->constrained($this->prefix.'pack_links')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create($this->prefix.'pack_link_row_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_link_row_id')->constrained($this->prefix.'pack_link_rows')->cascadeOnDelete();
            $table->foreignId('pack_item_option_id')->constrained($this->prefix.'pack_item_options')->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained($this->prefix.'product_option_values')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pack_link_row_id', 'pack_item_option_id']);
        });
    }

    public function down(): void
    {
        foreach (['pack_link_row_values', 'pack_link_rows', 'pack_item_options', 'pack_links', 'pack_items', 'packs'] as $table) {
            Schema::dropIfExists($this->prefix.$table);
        }
    }
};
