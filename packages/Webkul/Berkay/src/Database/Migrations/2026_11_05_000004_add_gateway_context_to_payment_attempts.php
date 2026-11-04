<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Preserve encrypted gateway configuration for pending transactions. */
    public function up(): void
    {
        Schema::table('berkay_payment_attempts', function (Blueprint $table) {
            $table->text('gateway_context')->nullable();
        });
    }

    /** Remove frozen provider configuration. */
    public function down(): void
    {
        Schema::table('berkay_payment_attempts', function (Blueprint $table) {
            $table->dropColumn('gateway_context');
        });
    }
};
