<?php

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Berkay\Http\Controllers\Admin\ArticleController;
use Webkul\Berkay\Http\Controllers\Admin\ArticleImportController;
use Webkul\Berkay\Http\Controllers\Admin\AttributeSettingsController;
use Webkul\Berkay\Http\Controllers\Admin\IntegrationController;
use Webkul\Berkay\Http\Controllers\Admin\KavenegarController;
use Webkul\Berkay\Http\Controllers\Admin\OperationsController;
use Webkul\Berkay\Http\Controllers\Admin\SimpleCatalogController;
use Webkul\Berkay\Http\Controllers\Admin\UserManagementController;
use Webkul\Berkay\Http\Controllers\Shop\ContentSitemapController;
use Webkul\Berkay\Http\Controllers\Shop\CustomRequestController;
use Webkul\Berkay\Http\Controllers\Shop\MagazineController;
use Webkul\Berkay\Http\Controllers\Shop\ProductCollectionController;
use Webkul\Berkay\Http\Controllers\Shop\StorefrontEditorController;
use Webkul\Berkay\Http\Controllers\Shop\TorobController;
use Webkul\Berkay\Http\Controllers\Shop\ZarinpalController;
use Webkul\Berkay\Http\Middleware\ProductEditorAccess;
use Webkul\Berkay\Services\ProductCollections;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

Route::middleware(['web', 'shop'])->group(function () {
    Route::get('berkay/home-collections', [ProductCollectionController::class, 'rows'])
        ->middleware(NoCacheMiddleware::class)->name('shop.berkay.collections.rows');
    Route::get('collections/{collection}', [ProductCollectionController::class, 'index'])
        ->whereIn('collection', ProductCollections::KEYS)
        ->name('shop.berkay.collections.index');
    Route::post('berkay/product-views/{product}', [ProductCollectionController::class, 'record'])
        ->whereNumber('product')->middleware('throttle:60,1')->name('shop.berkay.collections.record');
    Route::get('berkay/editor-session', [StorefrontEditorController::class, 'session'])->middleware(NoCacheMiddleware::class)->name('shop.berkay.editor_session');
    Route::get(config('app.admin_url').'/berkay/storefront/products/{id}/editor', [StorefrontEditorController::class, 'editor'])->whereNumber('id')->middleware([NoCacheMiddleware::class, ProductEditorAccess::class])->name('shop.berkay.full_editor');
    Route::put(config('app.admin_url').'/berkay/storefront/products/{id}/editor', [StorefrontEditorController::class, 'saveEditor'])->whereNumber('id')->middleware([NoCacheMiddleware::class, ProductEditorAccess::class, 'throttle:30,1'])->name('shop.berkay.full_editor.update');
    Route::patch('berkay/products/{id}/inline', [StorefrontEditorController::class, 'update'])->whereNumber('id')->middleware([NoCacheMiddleware::class, ProductEditorAccess::class, 'throttle:30,1'])->name('shop.berkay.inline_product');
    Route::get('content-sitemap.xml', [ContentSitemapController::class, 'index'])->name('shop.berkay.content_sitemap');
    Route::get('magazine', [MagazineController::class, 'index'])->name('shop.berkay.magazine.index');
    Route::get('magazine/{slug}', [MagazineController::class, 'show'])->name('shop.berkay.magazine.show');
    Route::get('custom-order', [CustomRequestController::class, 'create'])->name('shop.berkay.custom_order.create');
    Route::post('custom-order', [CustomRequestController::class, 'store'])
        ->middleware('throttle:3,10')
        ->name('shop.berkay.custom_order.store');
});

Route::middleware(['web', 'admin', NoCacheMiddleware::class])
    ->prefix(config('app.admin_url').'/berkay')
    ->group(function () {
        Route::get('/', [OperationsController::class, 'index'])->name('admin.berkay.index');
        Route::get('integrations', [IntegrationController::class, 'index'])->name('admin.berkay.integrations.index');
        Route::put('integrations', [IntegrationController::class, 'update'])->name('admin.berkay.integrations.update');
        Route::get('kavenegar', [KavenegarController::class, 'index'])->name('admin.berkay.kavenegar.index');
        Route::put('kavenegar', [KavenegarController::class, 'update'])->name('admin.berkay.kavenegar.update');
        Route::post('kavenegar/send', [KavenegarController::class, 'send'])
            ->middleware('throttle:8,1')->name('admin.berkay.kavenegar.send');
        Route::get('articles', [ArticleController::class, 'index'])->name('admin.berkay.articles.index');
        Route::get('articles/create', [ArticleController::class, 'create'])->name('admin.berkay.articles.create');
        Route::post('articles', [ArticleController::class, 'store'])->name('admin.berkay.articles.store');
        Route::get('articles/{id}/edit', [ArticleController::class, 'edit'])->whereNumber('id')->name('admin.berkay.articles.edit');
        Route::put('articles/{id}', [ArticleController::class, 'update'])->whereNumber('id')->name('admin.berkay.articles.update');
        Route::delete('articles/{id}', [ArticleController::class, 'destroy'])->whereNumber('id')->name('admin.berkay.articles.destroy');
        Route::get('custom-orders', [OperationsController::class, 'requests'])->name('admin.berkay.custom_orders.index');
        Route::get('custom-orders/{id}/images/{index}', [OperationsController::class, 'image'])->whereNumber(['id', 'index'])->name('admin.berkay.custom_orders.image');
        Route::put('custom-orders/{id}', [OperationsController::class, 'updateRequest'])->whereNumber('id')->name('admin.berkay.custom_orders.update');
    });

Route::middleware(['api', StartSession::class, 'shop', 'throttle:60,1'])
    ->post('torob_api/v3/products', [TorobController::class, 'index'])
    ->name('shop.berkay.torob.products');

Route::middleware(['web', 'shop', 'throttle:20,1'])->group(function () {
    Route::get('payments/zarinpal/resume/{token}', [ZarinpalController::class, 'resume'])->where('token', '[A-Za-z0-9]{64}')->name('shop.berkay.zarinpal.resume');
    Route::get('payments/zarinpal/start', [ZarinpalController::class, 'start'])->name('shop.berkay.zarinpal.start');
    Route::get('payments/zarinpal/callback/{token}', [ZarinpalController::class, 'callback'])->where('token', '[A-Za-z0-9]{64}')->name('shop.berkay.zarinpal.callback');
});

Route::middleware(['web', 'admin', NoCacheMiddleware::class])->prefix(config('app.admin_url').'/berkay')
    ->get('payments', [OperationsController::class, 'payments'])->name('admin.berkay.payments.index');

Route::middleware(['web', 'admin', NoCacheMiddleware::class])->prefix(config('app.admin_url').'/berkay')->group(function () {
    Route::get('new-product', [SimpleCatalogController::class, 'create'])->name('admin.berkay.simple_product.create');
    Route::post('new-product', [SimpleCatalogController::class, 'store'])->name('admin.berkay.simple_product.store');
    Route::get('new-category', [SimpleCatalogController::class, 'category'])->name('admin.berkay.simple_category.create');
    Route::post('new-category', [SimpleCatalogController::class, 'storeCategory'])->name('admin.berkay.simple_category.store');
});

Route::middleware(['web', 'admin', NoCacheMiddleware::class])->prefix(config('app.admin_url').'/berkay')->controller(SimpleCatalogController::class)->group(function () {
    Route::get('products', 'index')->name('admin.berkay.simple_product.index');
    Route::get('products/{id}/edit', 'edit')->whereNumber('id')->name('admin.berkay.simple_product.edit');
    Route::put('products/{id}', 'update')->whereNumber('id')->name('admin.berkay.simple_product.update');
    Route::get('categories', 'categories')->name('admin.berkay.simple_category.index');
    Route::get('categories/{id}/edit', 'editCategory')->whereNumber('id')->name('admin.berkay.simple_category.edit');
    Route::put('categories/{id}', 'updateCategory')->whereNumber('id')->name('admin.berkay.simple_category.update');
});

Route::middleware(['web', 'admin', NoCacheMiddleware::class, 'throttle:20,1'])->prefix(config('app.admin_url').'/berkay')->post('articles/import', [ArticleImportController::class, 'parse'])->name('admin.berkay.articles.parse');

Route::middleware(['web', 'admin', NoCacheMiddleware::class])->prefix(config('app.admin_url').'/berkay/users')->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->name('admin.berkay.users.index');
    Route::get('create', [UserManagementController::class, 'create'])->name('admin.berkay.users.create');
    Route::post('/', [UserManagementController::class, 'save'])->middleware('throttle:20,1')->name('admin.berkay.users.store');
    Route::get('{kind}/{id}/edit', [UserManagementController::class, 'edit'])->whereIn('kind', ['customers', 'staff'])->whereNumber('id')->name('admin.berkay.users.edit');
    Route::put('{kind}/{id}', [UserManagementController::class, 'save'])->whereIn('kind', ['customers', 'staff'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.berkay.users.update');
});

Route::middleware(['web', 'admin', NoCacheMiddleware::class])->get(config('app.admin_url').'/berkay/attributes', function () {
    abort_unless(bouncer()->hasPermission('catalog.attributes'), 403);
    $attributes = app(AttributeRepository::class);

    return view('berkay::admin.attribute-settings', ['color' => $attributes->findOneByField('code', 'color'), 'material' => $attributes->findOneByField('code', 'material')]);
})->name('admin.berkay.attributes.index');

Route::middleware(['web', 'admin', NoCacheMiddleware::class, 'throttle:60,1'])->post(config('app.admin_url').'/berkay/attributes/options', [AttributeSettingsController::class, 'save'])->name('admin.berkay.attributes.save');
