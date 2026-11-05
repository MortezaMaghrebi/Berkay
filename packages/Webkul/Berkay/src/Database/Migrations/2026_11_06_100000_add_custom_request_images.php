<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Store optional private reference image paths for bespoke inquiries. */
    public function up(): void
    {
        Schema::table('berkay_custom_requests', fn (Blueprint $table) => $table->json('images')->nullable());
    }

    /** Remove reference path metadata. */
    public function down(): void
    {
        Schema::table('berkay_custom_requests', fn (Blueprint $table) => $table->dropColumn('images'));
    }
};
