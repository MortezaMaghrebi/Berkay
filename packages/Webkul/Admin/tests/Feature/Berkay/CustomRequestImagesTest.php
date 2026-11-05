<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Berkay\Repositories\CustomRequestRepository;

it('stores multiple private reference photos and serves them only to permitted staff', function () {
    Storage::fake('private');
    Storage::fake('public');
    $this->setConfig('berkay.store.identity.custom_orders', 1);
    $this->post(route('shop.berkay.custom_order.store'), [
        'name' => 'Photo inquiry', 'phone' => '09123456789', 'product_type' => 'Bag',
        'description' => 'A custom bag based on these reference photos.',
        'images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.png')],
    ])->assertRedirect(route('shop.berkay.custom_order.create'));
    $inquiry = app(CustomRequestRepository::class)->findOneByField('name', 'Photo inquiry');
    expect($inquiry->images)->toHaveCount(2);
    foreach ($inquiry->images as $path) {
        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }
    $url = route('admin.berkay.custom_orders.image', [$inquiry->id, 0]);
    $this->get($url)->assertRedirect();
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.articles']);
    $this->get($url)->assertUnauthorized();
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.custom-orders']);
    $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('admin.berkay.custom_orders.image', [$inquiry->id, 9]))->assertNotFound();
});

it('rejects too many or executable reference photos without recording a request', function () {
    Storage::fake('private');
    $this->setConfig('berkay.store.identity.custom_orders', 1);
    $payload = ['name' => 'Invalid photo inquiry', 'phone' => '09123456789', 'product_type' => 'Bag', 'description' => 'A sufficiently detailed custom bag request.'];
    $this->post(route('shop.berkay.custom_order.store'), $payload + ['images' => array_map(fn () => UploadedFile::fake()->image('image.jpg'), range(1, 6))])->assertSessionHasErrors('images');
    $this->post(route('shop.berkay.custom_order.store'), $payload + ['images' => [UploadedFile::fake()->create('image.svg', 10, 'image/svg+xml')]])->assertSessionHasErrors('images.0');
    $this->post(route('shop.berkay.custom_order.store'), $payload + ['images' => [UploadedFile::fake()->image('large.jpg')->size(5000)]])->assertSessionHasErrors('images.0');
    expect(app(CustomRequestRepository::class)->findOneByField('name', $payload['name']))->toBeNull();
    expect(Storage::disk('private')->allFiles())->toBeEmpty();
});
