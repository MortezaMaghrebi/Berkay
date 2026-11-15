<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables carrying amounts from the cart through payment and fulfillment. */
    private const TABLES = [
        'cart', 'cart_items', 'cart_shipping_rates', 'orders', 'order_items',
        'invoices', 'invoice_items', 'shipment_items', 'refunds', 'refund_items', 'order_transactions',
    ];

    /** Increase the integer capacity while keeping four decimal places. */
    public function up(): void
    {
        $this->resize($this->columns(12), 18);
    }

    /** Restore the original capacity only when all stored values fit. */
    public function down(): void
    {
        $columnsByTable = $this->columns(18);

        foreach ($columnsByTable as $table => $columns) {
            foreach ($columns as $column) {
                if (DB::table($table)->whereNotBetween($column['name'], [-99999999.9999, 99999999.9999])->exists()) {
                    throw new RuntimeException('Checkout money precision cannot be reduced while larger amounts are stored.');
                }
            }
        }

        $this->resize($columnsByTable, 12);
    }

    /** Find decimal money columns of the specified precision. */
    private function columns(int $precision): array
    {
        $columnsByTable = [];

        foreach (self::TABLES as $table) {
            $columnsByTable[$table] = array_filter(Schema::getColumns($table), fn (array $column) => preg_match('/^(decimal|numeric)\('.$precision.',\s*4\)$/i', $column['type'])
                && ! preg_match('/(?:percent|weight|qty|exchange_rate)$/', $column['name']));
        }

        return $columnsByTable;
    }

    /** Preserve the nullability and numeric defaults of modified columns. */
    private function resize(array $columnsByTable, int $precision): void
    {
        foreach ($columnsByTable as $tableName => $columns) {
            if ($columns === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns, $precision) {
                foreach ($columns as $column) {
                    $definition = $table->decimal($column['name'], $precision, 4)->nullable($column['nullable']);
                    $default = trim((string) $column['default'], "'");

                    if (is_numeric($default)) {
                        $definition->default($default);
                    }

                    $definition->change();
                }
            });
        }
    }
};
