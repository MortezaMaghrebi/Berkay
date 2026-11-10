<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Webkul\Berkay\Support\PersianProductCopy;

return new class extends Migration
{
    /** Correct product text in the catalog and its storefront projection. */
    public function up(): void
    {
        $fields = ['name', 'short_description', 'description', 'meta_title', 'meta_description', 'meta_keywords'];
        $attributes = DB::table('attributes')->whereIn('code', $fields)->pluck('code', 'id');

        DB::table('product_attribute_values')->whereIn('attribute_id', $attributes->keys())
            ->whereNotNull('text_value')->select(['id', 'attribute_id', 'text_value'])
            ->chunkById(200, function ($rows) use ($attributes) {
                foreach ($rows as $row) {
                    $edited = PersianProductCopy::edit($row->text_value, $this->isRichText($attributes[$row->attribute_id]));

                    if ($edited !== $row->text_value) {
                        DB::table('product_attribute_values')->where('id', $row->id)
                            ->where('text_value', $row->text_value)->update(['text_value' => $edited]);
                    }
                }
            });

        DB::table('product_flat')->select(['id', ...$fields])->chunkById(200, function ($rows) use ($fields) {
            foreach ($rows as $row) {
                foreach ($fields as $field) {
                    if ($row->$field === null) {
                        continue;
                    }

                    $edited = PersianProductCopy::edit($row->$field, $this->isRichText($field));

                    if ($edited !== $row->$field) {
                        DB::table('product_flat')->where('id', $row->id)
                            ->where($field, $row->$field)->update([$field => $edited]);
                    }
                }
            }
        });

        DB::table('products')->whereNotNull('additional')->select('id')->chunkById(200, function ($rows) {
            DB::transaction(function () use ($rows) {
                $products = DB::table('products')->whereIn('id', $rows->pluck('id'))
                    ->orderBy('id')->lockForUpdate()->get(['id', 'additional']);

                foreach ($products as $product) {
                    $additional = json_decode($product->additional ?? '', true);
                    $details = $additional['berkay_variant'] ?? null;

                    if (! is_array($details)) {
                        continue;
                    }

                    if (is_string($details['color_description'] ?? null)) {
                        $details['color_description'] = PersianProductCopy::edit($details['color_description']);
                    }

                    if (is_array($details['features'] ?? null)) {
                        $details['features'] = array_map(
                            fn ($value) => is_string($value) ? PersianProductCopy::edit($value) : $value,
                            $details['features']
                        );
                    }

                    if ($details !== $additional['berkay_variant']) {
                        $additional['berkay_variant'] = $details;
                        DB::table('products')->where('id', $product->id)
                            ->update(['additional' => json_encode($additional, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                    }
                }
            });
        });
    }

    /** Keep copy edits when rolling back code. */
    public function down(): void {}

    /** Identify fields that may store formatted product content. */
    private function isRichText(string $field): bool
    {
        return in_array($field, ['description', 'short_description'], true);
    }
};
