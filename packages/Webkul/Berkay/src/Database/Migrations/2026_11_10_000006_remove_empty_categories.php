<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Webkul\Category\Models\CategoryProxy;

return new class extends Migration
{
    /** Remove empty category branches while retaining channel roots and all product assignments. */
    public function up(): void
    {
        $model = CategoryProxy::modelClass();

        DB::transaction(function () use ($model) {
            $roots = DB::table('channels')->whereNotNull('root_category_id')->pluck('root_category_id');
            $removed = true;

            while ($removed) {
                $leaves = $model::query()
                    ->whereNotIn('id', $roots)
                    ->whereDoesntHave('children')
                    ->whereDoesntHave('products')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                $removed = false;

                foreach ($leaves as $id) {
                    $category = $model::query()->whereKey($id)
                        ->whereDoesntHave('children')->whereDoesntHave('products')->first();

                    if ($category) {
                        $category->delete();
                        $removed = true;
                    }
                }
            }
        });
    }

    /** Deleted categories cannot be reconstructed from the remaining catalog. */
    public function down(): void {}
};
