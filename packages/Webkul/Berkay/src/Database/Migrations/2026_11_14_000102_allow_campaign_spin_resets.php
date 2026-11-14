<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('berkay_campaign_spins', function (Blueprint $table) {
            $table->string('attempt_slot', 24)->default('active');
            $table->timestamp('reset_at')->nullable();
            $table->unique(['run', 'customer_id', 'attempt_slot'], 'berkay_campaign_spin_attempt_unique');
        });

        Schema::table('berkay_campaign_spins', function (Blueprint $table) {
            $table->dropUnique(['run', 'customer_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('berkay_campaign_spins')->whereNotNull('reset_at')->exists()) {
            throw new RuntimeException('Cannot roll back campaign reset support while reset history exists.');
        }

        Schema::table('berkay_campaign_spins', function (Blueprint $table) {
            $table->unique(['run', 'customer_id']);
            $table->dropUnique('berkay_campaign_spin_attempt_unique');
            $table->dropColumn(['attempt_slot', 'reset_at']);
        });
    }
};
