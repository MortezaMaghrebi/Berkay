<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Replace unset legacy limits with the default threshold in tomans. */
    public function up(): void
    {
        DB::table('core_config')
            ->where('code', 'berkay.workshop.review.amount_limit')
            ->where(fn ($query) => $query->whereIn('value', ['', '0'])->orWhereNull('value'))
            ->update(['value' => '100000000']);
    }

    /** Preserve configured thresholds when rolling back. */
    public function down(): void {}
};
