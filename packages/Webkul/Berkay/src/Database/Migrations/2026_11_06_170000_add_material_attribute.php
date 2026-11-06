<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Repositories\AttributeRepository;

return new class extends Migration
{
    /** Add a reusable material selector to existing product families. */
    public function up(): void
    {
        $attributes = app(AttributeRepository::class);
        $material = $attributes->findOneByField('code', 'material') ?? $attributes->create([
            'code' => 'material', 'admin_name' => 'جنس', 'type' => 'select', 'swatch_type' => 'dropdown',
            'position' => 30, 'is_required' => 0, 'is_unique' => 0, 'is_filterable' => 1,
            'is_configurable' => 0, 'is_user_defined' => 1, 'is_visible_on_front' => 1,
            'value_per_locale' => 0, 'value_per_channel' => 0,
            'fa' => ['name' => 'جنس'], 'en' => ['name' => 'Material'],
            'options' => [],
        ]);
        foreach (DB::table('attribute_groups')->select('id', 'attribute_family_id')->orderBy('position')->get()->unique('attribute_family_id') as $group) {
            if (! DB::table('attribute_group_mappings')->where('attribute_group_id', $group->id)->where('attribute_id', $material->id)->exists()) {
                DB::table('attribute_group_mappings')->insert(['attribute_group_id' => $group->id, 'attribute_id' => $material->id, 'position' => 30]);
            }
        }
    }

    /** Keep merchant-maintained material values when rolling back. */
    public function down(): void {}
};
