<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a channel-specific override for each product's Torob selection.
     */
    public function up(): void
    {
        Schema::table('product_channels', function (Blueprint $table) {
            $table->boolean('torob_export')->nullable();
        });
    }

    /**
     * Remove the channel-specific Torob selection override.
     */
    public function down(): void
    {
        Schema::table('product_channels', function (Blueprint $table) {
            $table->dropColumn('torob_export');
        });
    }
};
