<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Normalize catalog SKUs without changing IDs, URLs or historical order lines. */
    public function up(): void
    {
        $skuAttribute = DB::table('attributes')->where('code', 'sku')->value('id');

        DB::table('products')->whereRaw('LOWER(sku) LIKE ?', ['%basalam%'])
            ->select(['id', 'sku'])->chunkById(200, function ($products) use ($skuAttribute) {
                DB::transaction(function () use ($products, $skuAttribute) {
                    foreach ($products as $product) {
                        $clean = trim(preg_replace('/basalam[-_ ]*/i', '', $product->sku), "-_ \t");
                        $sku = $clean !== '' ? $clean : 'BK-'.$product->id;

                        for ($suffix = 1; DB::table('products')->where('sku', $sku)->where('id', '!=', $product->id)->exists(); $suffix++) {
                            $ending = '-'.$product->id.($suffix > 1 ? '-'.$suffix : '');
                            $sku = mb_substr($clean !== '' ? $clean : 'BK', 0, 255 - strlen($ending)).$ending;
                        }

                        DB::table('products')->where('id', $product->id)->update(['sku' => $sku]);
                        DB::table('product_flat')->where('product_id', $product->id)->update(['sku' => $sku]);
                        DB::table('product_attribute_values')->where('product_id', $product->id)
                            ->where('attribute_id', $skuAttribute)->update(['text_value' => $sku]);
                    }
                });
            });
    }

    /** Original imported SKUs are intentionally discarded. */
    public function down(): void {}
};
