<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'shop'])->prefix('customer/social-login/{provider}')->group(function () {
    Route::get('', fn () => redirect()->route('shop.customer.session.index'))->name('customer.social_login.index');

    Route::get('callback', fn () => redirect()->route('shop.customer.session.index'))->name('customer.social_login.callback');
});
