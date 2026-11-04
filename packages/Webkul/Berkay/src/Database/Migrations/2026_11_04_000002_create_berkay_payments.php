<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Record server-side gateway attempts and unique payment receipts. */
    public function up(): void
    {
        Schema::create('berkay_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedInteger('order_id')->index();
            $table->unsignedInteger('cart_id')->index();
            $table->string('provider', 32);
            $table->string('authority')->unique();
            $table->unsignedBigInteger('amount_rial');
            $table->string('status')->default('pending')->index();
            $table->string('receipt')->nullable()->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /** Remove payment attempt storage only on an explicit rollback. */
    public function down(): void
    {
        Schema::dropIfExists('berkay_payment_attempts');
    }
};
