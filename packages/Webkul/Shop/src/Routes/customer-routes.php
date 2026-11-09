<?php

use Illuminate\Support\Facades\Route;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;
use Webkul\Shop\Http\Controllers\Customer\Account\AddressController;
use Webkul\Shop\Http\Controllers\Customer\Account\DownloadableProductController;
use Webkul\Shop\Http\Controllers\Customer\Account\EUWithdrawalController as CustomerEUWithdrawalController;
use Webkul\Shop\Http\Controllers\Customer\Account\OrderController;
use Webkul\Shop\Http\Controllers\Customer\Account\RMAController;
use Webkul\Shop\Http\Controllers\Customer\Account\WishlistController;
use Webkul\Shop\Http\Controllers\Customer\CustomerController;
use Webkul\Berkay\Http\Controllers\Shop\PhoneAccountController;
use Webkul\Shop\Http\Controllers\Customer\GDPRController;
use Webkul\Shop\Http\Controllers\Customer\SessionController;
use Webkul\Shop\Http\Controllers\DataGridController;
use Webkul\Shop\Http\Middleware\EnsureGDPRIsEnabled;

Route::prefix('customer')->group(function () {
    Route::controller(PhoneAccountController::class)->middleware(NoCacheMiddleware::class)->group(function () {
        Route::get('login', 'index')->name('shop.customer.session.index');
        Route::post('login', 'send')->middleware('throttle:10,1')->name('shop.customer.session.create');
        Route::post('login/verify', 'verify')->middleware('throttle:20,1')->name('shop.berkay.phone.login.verify');
        Route::get('register', 'index')->name('shop.customers.register.index');
        Route::post('register', 'send')->middleware('throttle:10,1')->name('shop.customers.register.store');
        Route::post('register/verify', 'verify')->middleware('throttle:20,1')->name('shop.berkay.phone.register.verify');
    });

    foreach ([
        ['forgot-password', 'shop.customers.forgot_password.create'],
        ['reset-password/{token}', 'shop.customers.reset_password.create'],
        ['verify-account/{token}', 'shop.customers.verify'],
        ['resend/verification/{email}', 'shop.customers.resend.verification_email'],
    ] as [$legacyPath, $legacyName]) {
        Route::get($legacyPath, fn () => redirect()->route('shop.customer.session.index'))->name($legacyName);
    }

    /**
     * Customer authenticated routes. All the below routes only be accessible
     * if customer is authenticated.
     */
    Route::group(['middleware' => ['customer', NoCacheMiddleware::class]], function () {
        /**
         * Datagrid routes.
         */
        Route::get('datagrid/look-up', [DataGridController::class, 'lookUp'])->name('shop.customer.datagrid.look_up');

        /**
         * Logout.
         */
        Route::delete('logout', [SessionController::class, 'destroy'])->name('shop.customer.session.destroy');

        /**
         * Customer account. All the below routes are related to
         * customer account details.
         */
        Route::prefix('account')->group(function () {
            Route::controller(PhoneAccountController::class)->prefix('phone/{purpose}')->where(['purpose' => 'change|delete'])->group(function () {
                Route::get('', 'settings')->name('shop.berkay.phone.settings');
                Route::post('', 'sendSettingsCode')->middleware('throttle:10,1')->name('shop.berkay.phone.settings.send');
                Route::post('verify', 'verifySettings')->middleware('throttle:20,1')->name('shop.berkay.phone.settings.verify');
            });
            Route::get('', [CustomerController::class, 'account'])->name('shop.customers.account.index');

            /**
             * Wishlist.
             */
            Route::get('wishlist', [WishlistController::class, 'index'])->name('shop.customers.account.wishlist.index');

            /**
             * Profile.
             */
            Route::controller(CustomerController::class)->group(function () {
                Route::prefix('profile')->group(function () {
                    Route::get('', 'index')->name('shop.customers.account.profile.index');

                    Route::get('edit', 'edit')->name('shop.customers.account.profile.edit');

                    Route::post('edit', 'update')->name('shop.customers.account.profile.update');


                });

                Route::get('reviews', 'reviews')->name('shop.customers.account.reviews.index');
            });

            /**
             * GDPR.
             */
            Route::controller(GDPRController::class)
                ->prefix('gdpr')
                ->middleware(EnsureGDPRIsEnabled::class)
                ->group(function () {
                    Route::get('', 'index')->name('shop.customers.account.gdpr.index');

                    Route::post('', 'store')->name('shop.customers.account.gdpr.store');

                    Route::get('pdf-view', 'pdfView')->name('shop.customers.account.gdpr.pdf_view');

                    Route::get('html-view', 'htmlView')->name('shop.customers.account.gdpr.html_view');

                    Route::post('revoke/{id}', 'revoke')->name('shop.customers.account.gdpr.revoke');
                });

            /**
             * Cookie consent.
             */
            Route::get('your-cookie-consent-preferences', [GDPRController::class, 'cookieConsent'])
                ->middleware(EnsureGDPRIsEnabled::class)
                ->name('shop.customers.gdpr.cookie_consent');

            /**
             * Addresses.
             */
            Route::controller(AddressController::class)->prefix('addresses')->group(function () {
                Route::get('', 'index')->name('shop.customers.account.addresses.index');

                Route::get('create', 'create')->name('shop.customers.account.addresses.create');

                Route::post('create', 'store')->name('shop.customers.account.addresses.store');

                Route::get('edit/{id}', 'edit')->name('shop.customers.account.addresses.edit');

                Route::put('edit/{id}', 'update')->name('shop.customers.account.addresses.update');

                Route::patch('edit/{id}', 'makeDefault')->name('shop.customers.account.addresses.update.default');

                Route::delete('delete/{id}', 'destroy')->name('shop.customers.account.addresses.delete');
            });

            /**
             * Orders.
             */
            Route::controller(OrderController::class)->prefix('orders')->group(function () {
                Route::get('', 'index')->name('shop.customers.account.orders.index');

                Route::get('view/{id}', 'view')->name('shop.customers.account.orders.view');

                Route::get('reorder/{id}', 'reorder')->name('shop.customers.account.orders.reorder');

                Route::post('cancel/{id}', 'cancel')->name('shop.customers.account.orders.cancel');

                Route::get('print/Invoice/{id}', 'printInvoice')->name('shop.customers.account.orders.print_invoice');
            });

            /**
             * Downloadable products.
             */
            Route::controller(DownloadableProductController::class)->prefix('downloadable-products')->group(function () {
                Route::get('', 'index')->name('shop.customers.account.downloadable_products.index');

                Route::get('download/{id}', 'download')->name('shop.customers.account.downloadable_products.download');
            });

            /**
             * Customer EU Withdrawal Routes (Directive (EU) 2023/2673, Art. 11a).
             */
            Route::controller(CustomerEUWithdrawalController::class)->group(function () {
                Route::get('orders/{orderId}/withdraw', 'create')
                    ->name('shop.customers.account.eu_withdrawal.create');

                Route::post('orders/{orderId}/withdraw', 'store')
                    ->name('shop.customers.account.eu_withdrawal.store');

                Route::get('withdrawals/{uuid}', 'show')
                    ->name('shop.customers.account.eu_withdrawal.show');
            });

            /**
             * Customer RMA Routes.
             */
            Route::controller(RMAController::class)->prefix('rma')->group(function () {
                Route::get('', 'index')->name('shop.customers.account.rma.index');

                Route::get('view/{id}', 'view')->name('shop.customers.account.rma.view');

                Route::get('create', 'create')->name('shop.customers.account.rma.create');

                Route::post('store', 'store')->name('shop.customers.account.rma.store');

                Route::get('get-order-items/{orderId}', 'getOrderItems')->name('shop.customers.account.rma.get_order_items');

                Route::get('get-resolution-reasons/{resolutionType}', 'getResolutionReasons')->name('shop.customers.account.rma.get_resolution_reasons');

                Route::post('update-status/{id}', 'updateStatus')->name('shop.customers.account.rma.update_status');

                Route::post('reopen/{id}', 'reOpenRequest')->name('shop.customers.account.rma.re_open');

                Route::post('cancel/{id}', 'cancelRequest')->name('shop.customers.account.rma.cancel');

                Route::get('get-messages', 'getMessages')->name('shop.customers.account.rma.get_messages');

                Route::get('attachments/{id}', 'downloadAttachment')->name('shop.customers.account.rma.attachment');

                Route::get('images/{id}', 'showImage')->name('shop.customers.account.rma.image');

                Route::post('send-message', 'sendMessage')->name('shop.customers.account.rma.send_message');
            });
        });
    });
});
