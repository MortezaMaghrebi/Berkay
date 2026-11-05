<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add an optional editorial cover without changing existing articles. */
    public function up(): void
    {
        Schema::table('berkay_articles', function (Blueprint $table) {
            $table->string('cover_path')->nullable();
            $table->string('cover_alt')->nullable();
        });
    }

    /** Remove only the editorial cover fields. */
    public function down(): void
    {
        Schema::table('berkay_articles', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'cover_alt']);
        });
    }
};
