<?php

use Illuminate\Support\Facades\Hash;
use Webkul\Customer\Models\Customer;
use Webkul\User\Models\Admin;

it('accepts admin credentials through the customer login and preserves admin authorization', function () {
    $admin = Admin::factory()->create(['password' => Hash::make('UnifiedLogin123!')]);
    $this->get(route('admin.session.create'))->assertRedirect(route('shop.customer.session.index'));
    $this->post(route('shop.customer.session.create'), ['email' => $admin->email, 'password' => 'UnifiedLogin123!'])->assertRedirect();
    $this->assertAuthenticatedAs($admin, 'admin');
    $this->assertGuest('customer');
});

it('does not authenticate disabled admins through the common login', function () {
    $admin = Admin::factory()->create(['status' => 0, 'password' => Hash::make('UnifiedLogin123!')]);
    $this->post(route('shop.customer.session.create'), ['email' => $admin->email, 'password' => 'UnifiedLogin123!'])->assertRedirect();
    $this->assertGuest('admin');
    $this->assertGuest('customer');
});

it('keeps customers signed in without granting administrative access', function () {
    $customer = Customer::factory()->create(['password' => Hash::make('UnifiedLogin123!'), 'status' => 1, 'is_verified' => 1, 'channel_id' => core()->getCurrentChannel()->id]);
    $this->post(route('shop.customer.session.create'), ['email' => $customer->email, 'password' => 'UnifiedLogin123!'])->assertRedirect();
    $this->assertAuthenticatedAs($customer, 'customer');
    $this->assertGuest('admin');
});
