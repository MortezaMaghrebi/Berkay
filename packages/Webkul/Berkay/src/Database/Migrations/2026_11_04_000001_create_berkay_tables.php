<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create magazine and bespoke request storage. */
    public function up(): void
    {
        Schema::create('berkay_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('berkay_custom_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('name', 120);
            $table->string('phone', 20);
            $table->string('product_type', 120);
            $table->text('description');
            $table->string('status')->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /** Remove extension storage when explicitly rolled back. */
    public function down(): void
    {
        Schema::dropIfExists('berkay_custom_requests');
        Schema::dropIfExists('berkay_articles');
    }
};
