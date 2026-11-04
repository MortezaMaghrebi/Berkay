<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add encrypted provider configuration without changing commerce records. */
    public function up(): void
    {
        Schema::create('berkay_integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('value');
            $table->timestamps();
        });
    }

    /** Remove the integration configuration table. */
    public function down(): void
    {
        Schema::dropIfExists('berkay_integration_settings');
    }
};
