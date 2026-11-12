<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->prefix.'packs', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('status');
            $table->boolean('discount_enabled')->default(false)->after('discount_percentage');
            $table->boolean('discount_badge')->default(false)->after('discount_enabled');
        });
    }

    public function down(): void
    {
        Schema::table($this->prefix.'packs', function (Blueprint $table) {
            $table->dropColumn(['discount_percentage', 'discount_enabled', 'discount_badge']);
        });
    }
};
