<?php

use Webkul\Checkout\Facades\Cart;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->addProductToCart($this->createSimpleProduct()->id);
});

it('should fail the validation when the billing state belongs to another country', function () {
    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => $this->storefrontAddress([
            'country' => 'IN',
            'state' => 'CA',
            'use_for_shipping' => true,
        ]),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing.state' => trans('validation.in', ['attribute' => 'billing.state'])]);
});

it('should fail the validation when the shipping state belongs to another country', function () {
    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => $this->storefrontAddress([
            'country' => 'US',
            'state' => 'CA',
            'use_for_shipping' => false,
        ]),
        'shipping' => $this->storefrontAddress([
            'country' => 'IN',
            'state' => 'CA',
        ]),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('shipping.state')
        ->assertJsonMissingValidationErrors('billing.state');
});

it('should fail the validation when the state is not in the state list of the selected country', function () {
    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => $this->storefrontAddress([
            'country' => 'IN',
            'state' => 'XX',
            'use_for_shipping' => true,
        ]),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('billing.state');
});

it('should store the address when the state belongs to the selected country or the country has no state list', function (string $country, string $state) {
    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => $this->storefrontAddress([
            'country' => $country,
            'state' => $state,
            'use_for_shipping' => true,
        ]),
    ])
        ->assertOk();

    $this->assertDatabaseHas('addresses', [
        'address_type' => 'cart_billing',
        'country' => $country,
        'state' => $state,
    ]);
})->with([
    'state from the country list' => ['US', 'CA'],
    'free-form state for a country without a list' => ['GB', 'Greater London'],
]);

it('should retain a full multiline street address when selecting a legacy saved address', function () {
    $street = "12 Main Street\nUnit 5";
    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => $this->storefrontAddress([
            'country' => 'US',
            'state' => 'CA',
            'address' => $street,
            'use_for_shipping' => true,
        ]),
    ])->assertOk();

    $this->assertDatabaseHas('addresses', [
        'address_type' => 'cart_billing',
        'address' => implode(PHP_EOL, explode("\n", $street)),
    ]);
});

it('should accept a freshly saved address and return the shipping methods', function (bool $legacyPayload) {
    $customer = $this->loginAsCustomer();
    Cart::initCart($customer);
    $cartId = $this->addProductToCart($this->createSimpleProduct()->id)->json('data.id');
    $address = postJson(route('shop.api.customers.account.addresses.store'), $this->storefrontAddress())
        ->assertOk()
        ->assertJsonPath('data.default_address', false)
        ->json('data');

    if ($legacyPayload) {
        $address['default_address'] = null;
    }

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => array_replace($address, ['use_for_shipping' => true]),
    ])
        ->assertOk()
        ->assertJsonPath('redirect', false)
        ->assertJsonPath('data.shippingMethods.berkay_post.rates.0.method', 'berkay_post_berkay_post');

    foreach (['cart_billing', 'cart_shipping'] as $type) {
        $this->assertDatabaseHas('addresses', [
            'address_type' => $type,
            'cart_id' => $cartId,
            'customer_id' => $customer->id,
            'parent_address_id' => $address['id'],
            'default_address' => 0,
        ]);
    }
})->with(['new response' => [false], 'legacy null flag' => [true]]);
