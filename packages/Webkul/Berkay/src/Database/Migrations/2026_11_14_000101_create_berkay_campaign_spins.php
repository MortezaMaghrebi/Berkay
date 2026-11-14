<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berkay_campaign_spins', function (Blueprint $table) {
            $table->id();
            $table->uuid('run');
            $table->unsignedInteger('channel_id');
            $table->unsignedInteger('customer_id');
            $table->string('event', 32);
            $table->json('prize');
            $table->string('coupon', 48)->nullable()->unique();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->unique(['run', 'customer_id']);
            $table->index(['channel_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkay_campaign_spins');
    }
};
