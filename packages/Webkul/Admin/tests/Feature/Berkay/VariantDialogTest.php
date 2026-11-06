<?php

use Illuminate\Support\Facades\Storage;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Product\Repositories\ProductRepository;

it('persists dialog details through native variant saving and snapshots them for orders', function () {
    Storage::fake(config('filesystems.default'));
    $repository = app(ProductRepository::class);
    $color = app(AttributeRepository::class)->findOneByField('code', 'color');
    $option = app(AttributeOptionRepository::class)->create(['attribute_id' => $color->id, 'admin_name' => 'Dialog test color', 'sort_order' => 0]);
    $product = $repository->create(['type' => 'configurable', 'sku' => 'DIALOG-'.uniqid(), 'attribute_family_id' => 1]);
    $product->super_attributes()->attach($color->id);
    $channel = core()->getCurrentChannel();
    $source = $channel->inventory_sources->first()->id;
    $details = ['color_description' => 'Warm brown', 'material' => 'Leather', 'features' => ['Lightweight', '', 'Hand sewn']];
    $payload = ['channel' => $channel->code, 'locale' => 'fa', 'sku' => $product->sku, 'name' => 'Dialog bag', 'url_key' => strtolower($product->sku), 'description' => 'Bag', 'short_description' => 'Bag', 'status' => 1, 'channels' => [$channel->id], 'variants' => ['variant_test' => ['sku' => $product->sku.'-color', 'name' => 'Brown edition', 'color' => $option->id, 'weight' => 0.5, 'price' => 120000, 'status' => 1, 'inventories' => [$source => 5], 'berkay_details' => $details]]];
    $repository->update($payload, $product->id);
    $parent = $repository->find($product->id)->fresh();
    $variant = $parent->variants->first();
    expect($variant->additional['berkay_variant']['features'])->toBe(['Lightweight', 'Hand sewn'])->and($variant->additional['berkay_variant']['material'])->toBe('Leather')->and((float) $variant->price)->toBe(120000.0)->and($variant->inventories->sum('qty'))->toBe(5);
    $cart = $parent->getTypeInstance()->getAdditionalOptions(['selected_configurable_option' => $variant->id]);
    expect($cart['attributes']['berkay_name']['option_label'])->toBe('Brown edition')->and($cart['attributes']['berkay_feature_1']['option_label'])->toBe('Hand sewn');
    $payload['variants'] = [$variant->id => array_replace($payload['variants']['variant_test'], ['price' => 150000, 'berkay_details' => array_replace($details, ['color_description' => 'Edited shade'])])];
    $repository->update($payload, $product->id);
    expect($variant->fresh()->additional['berkay_variant']['color_description'])->toBe('Edited shade')->and((float) $variant->fresh()->price)->toBe(150000.0);
});
