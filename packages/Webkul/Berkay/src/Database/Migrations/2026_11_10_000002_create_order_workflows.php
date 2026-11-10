<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berkay_order_workflows', function (Blueprint $table) {
            $table->unsignedInteger('order_id')->primary();
            $table->string('review', 16)->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('building_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkay_order_workflows');
    }
};
