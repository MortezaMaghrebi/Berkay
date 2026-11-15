<?php

use Illuminate\Support\Facades\Hash;
use Webkul\Customer\Models\Customer;
use Webkul\User\Models\Admin;

it('accepts admin credentials through the customer login and preserves admin authorization', function () {
    $admin = Admin::factory()->create(['password' => Hash::make('UnifiedLogin123!')]);
    $this->get(route('admin.session.create'))->assertRedirect(route('shop.customer.session.index', ['tab' => 'password']));
    $this->post(route('shop.customer.session.password'), ['email' => $admin->email, 'password' => 'UnifiedLogin123!'])->assertRedirect(route('admin.dashboard.index'));
    $this->assertAuthenticatedAs($admin, 'admin');
    $this->assertGuest('customer');
});

it('does not authenticate disabled admins through the common login', function () {
    $admin = Admin::factory()->create(['status' => 0, 'password' => Hash::make('UnifiedLogin123!')]);
    $this->post(route('shop.customer.session.password'), ['email' => $admin->email, 'password' => 'UnifiedLogin123!'])->assertRedirect();
    $this->assertGuest('admin');
    $this->assertGuest('customer');
});

it('keeps customers signed in without granting administrative access', function () {
    $customer = Customer::factory()->create(['password' => Hash::make('UnifiedLogin123!'), 'status' => 1, 'is_verified' => 1, 'channel_id' => core()->getCurrentChannel()->id]);
    $this->post(route('shop.customer.session.password'), ['email' => $customer->email, 'password' => 'UnifiedLogin123!'])->assertRedirect(route('shop.customers.account.orders.index'));
    $this->assertAuthenticatedAs($customer, 'customer');
    $this->assertGuest('admin');
});

it('signs in a verified phone-only customer using the password tab', function () {
    $customer = Customer::factory()->create([
        'email' => null,
        'phone' => '09125551234',
        'password' => Hash::make('UnifiedLogin123!'),
        'status' => 1,
        'is_verified' => 1,
        'is_suspended' => 0,
        'channel_id' => core()->getCurrentChannel()->id,
    ]);

    $this->post(route('shop.customer.session.password'), [
        'email' => '۰۹۱۲۵۵۵۱۲۳۴',
        'password' => 'UnifiedLogin123!',
    ])->assertRedirect(route('shop.customers.account.orders.index'));

    $this->assertAuthenticatedAs($customer, 'customer');
    $this->assertGuest('admin');
});

it('lets an authorized administrator set a phone-only customer password without changing it on ordinary edits', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $customer = Customer::factory()->create([
        'email' => null,
        'phone' => '09125551235',
        'gender' => null,
        'password' => null,
    ]);

    $profile = $customer->only(['first_name', 'last_name', 'phone']);
    $update = route('admin.customers.customers.update', $customer->id);

    $this->putJson($update, $profile + [
        'password' => 'UnifiedLogin123!',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->putJson($update, $profile + [
        'password' => 'UnifiedLogin123!',
        'password_confirmation' => 'UnifiedLogin123!',
    ])->assertOk();

    $hash = $customer->refresh()->password;
    expect(Hash::check('UnifiedLogin123!', $hash))->toBeTrue();

    $this->putJson($update, $profile + ['password' => ''])->assertOk();

    expect($customer->refresh()->password)->toBe($hash);
});
