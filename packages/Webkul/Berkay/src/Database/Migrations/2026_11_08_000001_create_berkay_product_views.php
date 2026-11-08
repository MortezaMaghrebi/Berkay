<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berkay_product_views', function (Blueprint $table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('channel_id');
            $table->unsignedBigInteger('total')->default(0);

            $table->primary(['product_id', 'channel_id']);
            $table->index(['channel_id', 'total']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkay_product_views');
    }
};
