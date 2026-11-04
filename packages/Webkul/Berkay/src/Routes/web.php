<?php

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Webkul\Berkay\Http\Controllers\Admin\ArticleController;
use Webkul\Berkay\Http\Controllers\Admin\ArticleImportController;
use Webkul\Berkay\Http\Controllers\Admin\IntegrationController;
use Webkul\Berkay\Http\Controllers\Admin\OperationsController;
use Webkul\Berkay\Http\Controllers\Admin\SimpleCatalogController;
use Webkul\Berkay\Http\Controllers\Shop\CustomRequestController;
use Webkul\Berkay\Http\Controllers\Shop\MagazineController;
use Webkul\Berkay\Http\Controllers\Shop\TorobController;
use Webkul\Berkay\Http\Controllers\Shop\ZarinpalController;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

Route::middleware(['web', 'shop'])->group(function () {
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
        Route::get('articles', [ArticleController::class, 'index'])->name('admin.berkay.articles.index');
        Route::get('articles/create', [ArticleController::class, 'create'])->name('admin.berkay.articles.create');
        Route::post('articles', [ArticleController::class, 'store'])->name('admin.berkay.articles.store');
        Route::get('articles/{id}/edit', [ArticleController::class, 'edit'])->whereNumber('id')->name('admin.berkay.articles.edit');
        Route::put('articles/{id}', [ArticleController::class, 'update'])->whereNumber('id')->name('admin.berkay.articles.update');
        Route::delete('articles/{id}', [ArticleController::class, 'destroy'])->whereNumber('id')->name('admin.berkay.articles.destroy');
        Route::get('custom-orders', [OperationsController::class, 'requests'])->name('admin.berkay.custom_orders.index');
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
