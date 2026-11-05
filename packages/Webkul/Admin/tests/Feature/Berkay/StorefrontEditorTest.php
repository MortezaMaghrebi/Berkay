<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Berkay\Repositories\SellerCatalogRepository;
use Webkul\Category\Repositories\CategoryRepository;

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
    $category = app(CategoryRepository::class)->create(['name' => 'Inline category', 'slug' => 'inline-test-category', 'parent_id' => core()->getCurrentChannel()->root_category_id, 'position' => 1, 'status' => 1, 'locale' => 'fa']);
    $this->inlineProduct = app(SellerCatalogRepository::class)->saveSimple(['name' => 'Inline test bag', 'price' => 250000, 'quantity' => 4, 'category_id' => $category->id, 'status' => 1, 'description' => 'Original description', 'images' => [UploadedFile::fake()->image('bag.jpg')]]);
});

it('never returns editor capabilities to guests or permits guest writes', function () {
    $this->getJson(route('shop.berkay.editor_session', ['product_id' => $this->inlineProduct->id]))->assertOk()->assertExactJson(['admin' => false]);
    $this->patchJson(route('shop.berkay.inline_product', $this->inlineProduct->id), ['name' => 'Changed', 'short_description' => 'Changed'])->assertUnauthorized();
});

it('requires product edit permission and preserves product relations during inline saves', function () {
    $product = $this->inlineProduct;
    $payload = ['name' => 'Updated bag', 'short_description' => '<script>alert(1)</script>Plain text', 'price' => 1, 'categories' => []];
    $this->loginAsAdminWithPermissions(['catalog', 'catalog.products']);
    $this->getJson(route('shop.berkay.editor_session', ['product_id' => $product->id]))->assertOk()->assertJsonMissing(['editable' => true]);
    $this->patchJson(route('shop.berkay.inline_product', $product->id), $payload)->assertForbidden();
    $this->loginAsAdminWithPermissions(['catalog', 'catalog.products', 'catalog.products.edit']);
    $this->getJson(route('shop.berkay.editor_session', ['product_id' => $product->id]))->assertOk()->assertJsonPath('editable', true)->assertJsonStructure(['csrf', 'edit_url', 'save_url']);
    $categories = $product->categories->pluck('id')->all();
    $this->patchJson(route('shop.berkay.inline_product', $product->id), $payload)->assertOk();
    $fresh = app(SellerCatalogRepository::class)->storeProduct($product->id);
    expect($fresh->name)->toBe('Updated bag')->and($fresh->short_description)->not->toContain('<script>')
        ->and((float) $fresh->price)->toBe(250000.0)->and($fresh->images)->toHaveCount(1)
        ->and($fresh->inventories->sum('qty'))->toBe(4)->and($fresh->categories->pluck('id')->all())->toBe($categories);
});

it('protects the embedded editor and only permits framing its authorized form', function () {
    $url = route('shop.berkay.full_editor', $this->inlineProduct->id);
    $this->getJson($url)->assertUnauthorized();
    $this->putJson($url, [])->assertUnauthorized();
    $this->loginAsAdminWithPermissions(['catalog', 'catalog.products']);
    $this->getJson($url)->assertForbidden();
    $this->putJson($url, [])->assertForbidden();
    $this->loginAsAdminWithPermissions(['catalog', 'catalog.products', 'catalog.products.edit']);
    $this->get($url)->assertOk()->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertSee('name="sku"', false)->assertSee('multipart/form-data', false);
    $this->putJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['sku', 'name']);
    $this->putJson(route('shop.berkay.full_editor', 999999999), [])->assertNotFound();
});

it('saves the complete native form and returns to the embedded editor', function () {
    $this->loginAsAdminWithPermissions(['catalog', 'catalog.products', 'catalog.products.edit']);
    $product = $this->inlineProduct;
    $source = core()->getCurrentChannel()->inventory_sources->first();
    $payload = [
        'channel' => core()->getCurrentChannel()->code, 'locale' => 'fa',
        'sku' => $product->sku, 'name' => 'Full editor bag', 'url_key' => $product->url_key,
        'price' => 310000, 'weight' => '0.5', 'short_description' => '<p>Short copy</p>',
        'description' => '<p>Detailed copy</p>', 'meta_title' => 'Product SEO title',
        'status' => 1, 'visible_individually' => 1, 'manage_stock' => 1,
        'channels' => [core()->getCurrentChannel()->id], 'categories' => $product->categories->pluck('id')->all(),
        'inventories' => [$source->id => 7],
        'images' => ['files' => $product->images->mapWithKeys(fn ($image) => [$image->id => null])->all()],
    ];
    $url = route('shop.berkay.full_editor', $product->id);
    $this->put($url, $payload)->assertSessionHasNoErrors()->assertRedirect($url);
    $fresh = app(SellerCatalogRepository::class)->storeProduct($product->id);
    expect($fresh->name)->toBe('Full editor bag')->and((float) $fresh->price)->toBe(310000.0)
        ->and($fresh->meta_title)->toBe('Product SEO title')->and($fresh->images)->toHaveCount(1)
        ->and($fresh->inventories->sum('qty'))->toBe(7);
});

it('blocks revoked staff and incomplete two-factor sessions from storefront editing', function () {
    $admin = $this->loginAsAdmin();
    $url = route('shop.berkay.inline_product', $this->inlineProduct->id);
    $admin->update(['status' => 0]);
    $this->getJson(route('shop.berkay.editor_session'))->assertExactJson(['admin' => false]);
    $this->patchJson($url, [])->assertForbidden();
    $admin->update(['status' => 1, 'two_factor_enabled' => true]);
    $this->getJson(route('shop.berkay.editor_session'))->assertExactJson(['admin' => false]);
    $this->patchJson($url, [])->assertForbidden();
    $this->withSession(['two_factor_passed_for' => $admin->id])->getJson(route('shop.berkay.editor_session'))->assertJsonPath('admin', true);
});
