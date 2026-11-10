<?php

use Illuminate\Support\Facades\DB;
use Webkul\Berkay\Services\ShipmentQuantity;

it('sets unlimited stock from the table and restores the previous stock limit', function () {
    $this->loginAsAdmin();
    $product = $this->createSimpleProduct();
    $product->inventories()->update(['qty' => 2]);

    $this->get(route('admin.berkay.catalog_table.index'))
        ->assertOk()
        ->assertSee('name="unlimited_stock"', false)
        ->assertSee(route('shop.product_or_category.index', $product->url_key));

    $save = fn (bool $unlimited) => $this->patchJson(route('admin.berkay.catalog_table.update'), [
        'rows' => [['key' => 'product', 'id' => $product->id, 'parent' => $product->id, 'unlimited_stock' => $unlimited]],
    ])->assertOk()->assertJsonPath('rows.0.stock.'.$product->id, $unlimited);

    $save(true);
    expect($product->fresh()->haveSufficientQuantity(100))->toBeTrue()
        ->and($product->fresh()->isSaleable())->toBeTrue();

    $order = $this->createOrder(items: [['product' => $product->fresh(), 'qty_ordered' => 5]]);
    expect(app(ShipmentQuantity::class)->maximum($order->items->first(), $this->defaultInventorySourceId()))->toBe(5);

    $save(false);
    expect($product->fresh()->haveSufficientQuantity(3))->toBeFalse()
        ->and($product->fresh()->inventories->sum('qty'))->toBe(2);
});

it('applies a configurable product stock toggle to each of its variants', function () {
    $this->loginAsAdmin();
    $product = $this->createConfigurableProduct();

    $this->patchJson(route('admin.berkay.catalog_table.update'), [
        'rows' => [['key' => 'parent', 'id' => $product->id, 'parent' => $product->id, 'unlimited_stock' => true]],
    ])->assertOk()->assertJsonPath('rows.0.stock.'.$product->id, true);

    expect($product->fresh()->variants->every(fn ($variant) => ! $variant->manage_stock))->toBeTrue();
});

it('removes the import marker from catalog SKUs without changing product URLs or colliding with existing codes', function () {
    $imported = $this->createSimpleProduct();
    $existing = $this->createSimpleProduct();
    $oldUrl = $imported->url_key;

    DB::table('products')->where('id', $existing->id)->update(['sku' => '12345']);
    DB::table('products')->where('id', $imported->id)->update(['sku' => 'BASALAM-12345']);
    DB::table('product_flat')->where('product_id', $imported->id)->update(['sku' => 'BASALAM-12345']);

    $migration = require base_path('packages/Webkul/Berkay/src/Database/Migrations/2026_11_10_000004_remove_basalam_from_product_skus.php');
    $migration->up();
    $migration->up();

    $sku = '12345-'.$imported->id;
    expect($imported->fresh()->sku)->toBe($sku)
        ->and($imported->fresh()->url_key)->toBe($oldUrl)
        ->and($existing->fresh()->sku)->toBe('12345');
    $this->assertDatabaseHas('product_flat', ['product_id' => $imported->id, 'sku' => $sku]);
});
