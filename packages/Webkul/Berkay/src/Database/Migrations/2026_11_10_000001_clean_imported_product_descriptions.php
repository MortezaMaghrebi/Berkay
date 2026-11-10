<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $attributes = DB::table('attributes')->whereIn('code', ['description', 'short_description'])->pluck('id');

        DB::table('product_attribute_values as values')
            ->join('products', 'products.id', '=', 'values.product_id')
            ->where('products.sku', 'like', 'BASALAM-%')
            ->whereIn('values.attribute_id', $attributes)
            ->whereNotNull('values.text_value')
            ->select(['values.id', 'values.text_value'])
            ->chunkById(200, function ($values) {
                foreach ($values as $value) {
                    $clean = $this->clean($value->text_value);

                    if ($clean !== $value->text_value) {
                        DB::table('product_attribute_values')->where('id', $value->id)->update(['text_value' => $clean]);
                    }
                }
            }, 'values.id', 'id');

        DB::table('product_flat')->where('sku', 'like', 'BASALAM-%')
            ->select(['id', 'description', 'short_description'])->chunkById(200, function ($products) {
                foreach ($products as $product) {
                    $changes = [];
                    foreach (['description', 'short_description'] as $field) {
                        if ($product->$field !== null && ($clean = $this->clean($product->$field)) !== $product->$field) {
                            $changes[$field] = $clean;
                        }
                    }
                    if ($changes) {
                        DB::table('product_flat')->where('id', $product->id)->update($changes);
                    }
                }
            });
    }

    public function down(): void
    {
        // Removed import annotations are not recreated.
    }

    private function clean(string $html): string
    {
        $line = '(?:<br\s*/?>\s*|\r?\n)';
        $metadata = '(?:[^\r\n<>]*[—–]\s*موجودی\s*[:：]\s*[\pN٬,]+\s*[—–]\s*قیمت\s*[:：][^\r\n<>]*|(?:موجودی(?:\s+(?:باسلام|محصول|کالا))?|تعداد(?:\s+(?:باسلام|محصول|کالا|موجود)))\s*[:：]\s*[\pN٬,]+\s*(?:عدد|تا)?[ \t]*)';

        $html = preg_replace('~(?:'.$line.')'.$metadata.'(?='.$line.'|</p>|$)~iu', '', $html) ?? $html;

        return preg_replace('~(^|<p(?:\s[^>]*)?>)'.$metadata.'(?:'.$line.'|(?=</p>|$))~iu', '$1', $html) ?? $html;
    }
};
