<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Webkul\Berkay\Repositories\UserManagementRepository;
use Webkul\Customer\Models\Customer;
use Webkul\User\Models\Admin;

it('denies credential management to guests and custom roles even with a users permission', function () {
    $this->get(route('admin.berkay.users.index'))->assertRedirect(route('admin.session.create'));
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.users']);
    $this->get(route('admin.berkay.users.index'))->assertForbidden();
    $this->postJson(route('admin.berkay.users.store'), [])->assertForbidden();
});

it('updates a customer without changing a blank password and rejects weak passwords', function () {
    $this->loginAsAdmin();
    $customer = Customer::factory()->create();
    $oldHash = $customer->password;
    $url = route('admin.berkay.users.update', ['kind' => 'customers', 'id' => $customer->id]);
    $data = ['first_name' => 'Updated', 'last_name' => 'Customer', 'email' => $customer->email, 'phone' => '09123456789', 'status' => 1];
    $this->put($url, $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($customer->fresh()->password)->toBe($oldHash)->and($customer->fresh()->first_name)->toBe('Updated');
    $this->putJson($url, $data + ['password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->put($url, $data + ['password' => 'LongNewPassword123!', 'password_confirmation' => 'LongNewPassword123!'])->assertSessionHasNoErrors();
    expect(Hash::check('LongNewPassword123!', $customer->fresh()->password))->toBeTrue();
});

it('creates staff access for a customer and never overwrites an existing administrator', function () {
    $owner = $this->loginAsAdmin();
    $customer = Customer::factory()->create();
    $url = route('admin.berkay.users.update', ['kind' => 'customers', 'id' => $customer->id]);
    $data = ['first_name' => 'New', 'last_name' => 'Manager', 'email' => $customer->email, 'status' => 1, 'grant_admin' => 1, 'role_id' => $owner->role_id, 'password' => 'LongNewPassword123!', 'password_confirmation' => 'LongNewPassword123!'];
    $this->put($url, $data)->assertSessionHasNoErrors()->assertRedirect(route('admin.berkay.users.index', ['kind' => 'staff']));
    $staff = app(UserManagementRepository::class)->staffForEmail($customer->email);
    expect($staff)->not->toBeNull()->and(Hash::check($data['password'], $staff->password))->toBeTrue()->and($staff->role_id)->toBe($owner->role_id);
    $this->putJson($url, array_replace($data, ['first_name' => 'Should rollback']))->assertUnprocessable()->assertJsonValidationErrors('grant_admin');
    expect($customer->fresh()->first_name)->toBe('New');
});

it('creates and edits staff while rejecting self lockout and preserving the last owner', function () {
    $owner = $this->loginAsAdmin();
    $data = ['name' => 'Store helper', 'email' => 'helper-users-test@example.com', 'status' => 1, 'role_id' => $owner->role_id, 'password' => 'LongNewPassword123!', 'password_confirmation' => 'LongNewPassword123!'];
    $this->post(route('admin.berkay.users.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $staff = app(UserManagementRepository::class)->staffForEmail($data['email']);
    $this->get(route('admin.berkay.users.edit', ['kind' => 'staff', 'id' => $staff->id]))->assertOk()->assertDontSee($staff->password);
    $this->put(route('admin.berkay.users.update', ['kind' => 'staff', 'id' => $staff->id]), array_replace($data, ['status' => 0]))->assertSessionHasNoErrors();
    expect((int) $staff->fresh()->status)->toBe(0);
    $this->putJson(route('admin.berkay.users.update', ['kind' => 'staff', 'id' => $owner->id]), ['name' => $owner->name, 'email' => $owner->email, 'role_id' => $owner->role_id, 'status' => 0])->assertUnprocessable();
    $allOwners = Admin::whereHas('role', fn ($q) => $q->where('permission_type', 'all'))->where('id', '!=', $owner->id)->get();
    foreach ($allOwners as $other) {
        $other->update(['status' => 0]);
    }
    expect(fn () => app(UserManagementRepository::class)->save('staff', $owner->id, ['name' => $owner->name, 'email' => $owner->email, 'role_id' => $owner->role_id, 'status' => 0]))->toThrow(ValidationException::class);
});
