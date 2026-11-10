<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Berkay\Repositories\ArticleRepository;
use Webkul\Berkay\Repositories\SellerCatalogRepository;
use Webkul\Berkay\Repositories\SellerCategoryRepository;
use Webkul\Berkay\Repositories\SellerOverviewRepository;
use Webkul\Category\Repositories\CategoryRepository;

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
    $root = core()->getCurrentChannel()->root_category_id;
    $this->sellerCategory = app(CategoryRepository::class)->create([
        'name' => 'Seller category', 'slug' => 'seller-test-category', 'parent_id' => $root,
        'position' => 1, 'status' => 1, 'display_mode' => 'products_and_description', 'locale' => 'fa',
    ]);
});

it('lets a permitted seller create a product with automatic SKU and Persian prices', function () {
    $this->loginAsAdminWithPermissions(['catalog', 'catalog.products', 'catalog.products.create', 'catalog.products.edit']);
    $this->get(route('admin.berkay.simple_product.create'))->assertOk();
    $response = $this->post(route('admin.berkay.simple_product.store'), [
        'name' => 'کیف آزمایشی فروشنده', 'price' => '۲۵۰٬۰۰۰', 'quantity' => '۳',
        'category_id' => $this->sellerCategory->id, 'status' => 1,
        'images' => [UploadedFile::fake()->image('bag.jpg')],
    ]);
    $response->assertSessionHasNoErrors()->assertRedirect();
    $product = app(SellerCatalogRepository::class)->listing('کیف آزمایشی فروشنده')->first();
    expect($product)->not->toBeNull()->and($product->sku)->toStartWith('BK-')
        ->and((float) $product->price)->toBe(250000.0)
        ->and($product->images)->toHaveCount(1)
        ->and($product->inventories->sum('qty'))->toBe(3);
    $this->get(route('admin.berkay.simple_product.edit', $product->id))->assertOk();
    $this->put(route('admin.berkay.simple_product.update', $product->id), [
        'name' => 'کیف آزمایشی فروشنده', 'price' => '۳۰۰۰۰۰', 'quantity' => '۲',
        'category_id' => $this->sellerCategory->id, 'status' => 1,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($product->fresh()->images)->toHaveCount(1)
        ->and((float) $product->fresh()->price)->toBe(300000.0)
        ->and($product->fresh()->sku)->toBe($product->sku);

    $this->put(route('admin.berkay.simple_product.update', $product->id), [
        'name' => $product->name,
        'price' => 300000,
        'unlimited_stock' => 1,
        'category_id' => $this->sellerCategory->id,
        'status' => 1,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($product->fresh()->haveSufficientQuantity(100))->toBeTrue()
        ->and($product->fresh()->inventories->sum('qty'))->toBe(2);
});

it('sets, keeps and clears a percentage discount through the seller product editor', function () {
    $this->loginAsAdmin();
    $product = $this->createSimpleProduct();
    $url = route('admin.berkay.simple_product.update', $product->id);
    $data = ['name' => $product->name, 'price' => 200000, 'quantity' => 3, 'category_id' => $this->sellerCategory->id, 'status' => 1];

    $this->put($url, $data + ['discount_percent' => '۱۲٫۵'])->assertSessionHasNoErrors();
    expect((float) $product->fresh()->special_price)->toBe(175000.0);

    $this->put($url, $data + ['discount_percent' => ''])->assertSessionHasNoErrors();
    expect((float) $product->fresh()->special_price)->toBe(175000.0);

    $this->put($url, $data + ['discount_percent' => 0])->assertSessionHasNoErrors();
    expect($product->fresh()->special_price)->toBeNull();
});

it('rejects product writes from a seller with read-only access', function () {
    $this->loginAsAdminWithPermissions(['catalog', 'catalog.products']);
    $this->get(route('admin.berkay.simple_product.index'))->assertOk();
    $this->post(route('admin.berkay.simple_product.store'), [])->assertUnauthorized();
});

it('creates and edits categories without position or URL fields and rejects a cycle', function () {
    $this->loginAsAdminWithPermissions(['catalog', 'catalog.categories', 'catalog.categories.create', 'catalog.categories.edit']);
    $this->post(route('admin.berkay.simple_category.store'), [
        'name' => 'زیرمجموعه نمونه', 'parent_id' => $this->sellerCategory->id, 'status' => 1,
        'image' => UploadedFile::fake()->image('category.jpg'),
    ])->assertSessionHasNoErrors()->assertRedirect();
    $child = app(SellerCategoryRepository::class)->findWhere(['parent_id' => $this->sellerCategory->id])->first();
    expect($child)->not->toBeNull()->and($child->logo_path)->not->toBeNull();
    $photo = $child->logo_path;
    $this->put(route('admin.berkay.simple_category.update', $child->id), [
        'name' => 'نام تازه', 'parent_id' => $this->sellerCategory->id, 'status' => 1,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($child->fresh()->logo_path)->toBe($photo);
    $this->put(route('admin.berkay.simple_category.update', $this->sellerCategory->id), [
        'name' => 'Parent', 'parent_id' => $child->id, 'status' => 1,
    ])->assertSessionHasErrors('parent_id');
});

it('validates and sanitizes AI article JSON without saving or publishing it', function () {
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.articles', 'berkay.articles.create']);
    $payload = ['title' => 'Article', 'slug' => 'ai-test', 'category' => 'Care', 'excerpt' => 'Summary',
        'body' => '<h2>Useful heading</h2><p onclick="alert(1)">Article</p><script>alert(1)</script><img src="https://example.com/tracker">',
        'meta_title' => 'SEO title', 'meta_description' => 'SEO description'];
    $count = app(ArticleRepository::class)->all()->count();
    $response = $this->postJson(route('admin.berkay.articles.parse'), ['payload' => json_encode($payload)]);
    $response->assertOk()->assertJsonPath('data.slug', 'ai-test');
    expect($response->json('data.body'))->toContain('<h2>')->not->toContain('<script', 'onclick', '<img');
    expect(app(ArticleRepository::class)->all()->count())->toBe($count);
    $this->postJson(route('admin.berkay.articles.parse'), ['payload' => '{bad json'])->assertUnprocessable();
});

it('shows real order counts for this store without counting unrelated orders', function () {
    $overview = app(SellerOverviewRepository::class);
    $before = $overview->orderCounts();
    $this->createOrder(['status' => 'completed', 'channel_id' => core()->getCurrentChannel()->id]);
    $this->createOrder(['status' => 'pending', 'channel_id' => null]);
    $after = $overview->orderCounts();

    expect($after['completed'])->toBe($before['completed'] + 1)
        ->and($after['pending'])->toBe($before['pending']);

    $this->loginAsAdminWithPermissions(['berkay', 'berkay.overview']);
    $this->get(route('admin.berkay.index'))->assertOk()
        ->assertDontSee(trans('berkay::app.refresh.order-overview'));
});
