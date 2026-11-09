<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Webkul\Berkay\Services\KavenegarSms;

return new class extends Migration
{
    public function up(): void
    {
        $sms = app(KavenegarSms::class);

        DB::table('customers')->select(['id', 'phone'])->whereNotNull('phone')->chunkById(200, function ($customers) use ($sms) {
            foreach ($customers as $customer) {
                try {
                    $phone = $sms->normalizePhone($customer->phone);
                } catch (InvalidArgumentException) {
                    continue;
                }

                if ($phone === $customer->phone || DB::table('customers')->where('phone', $phone)->exists()) {
                    continue;
                }

                DB::table('customers')->where('id', $customer->id)->update(['phone' => $phone]);
            }
        });
    }

    public function down(): void
    {
        // Normalized phone numbers remain valid account identifiers.
    }
};
