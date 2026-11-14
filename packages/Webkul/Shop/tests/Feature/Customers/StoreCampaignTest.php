<?php

use Webkul\Berkay\Services\StoreCampaign;
use Webkul\CartRule\Models\CartRuleCoupon;
use Webkul\Customer\Models\Customer;

it('issues only one persisted coupon per customer and rejects a stale run', function () {
    $campaign = app(StoreCampaign::class);
    $channel = core()->getCurrentChannel();
    $group = core()->getGuestCustomerGroup();
    $customer = Customer::factory()->create([
        'channel_id' => $channel->id,
        'customer_group_id' => $group->id,
    ]);
    $settings = [
        'event' => 'yalda', 'wheel' => true, 'days' => 7,
        'prizes' => [
            ['label' => '۱۰ درصد', 'type' => 'percent', 'value' => 10, 'weight' => 1],
            ['label' => '۲۰ درصد', 'type' => 'percent', 'value' => 20, 'weight' => 1],
        ],
    ];
    $campaign->save($settings);
    $run = $campaign->active()['run'];

    expect($campaign->spin($run, $customer))->toBeTrue()
        ->and($campaign->spin($run, $customer))->toBeFalse();

    $result = $campaign->result($run, $customer->id);
    $coupon = CartRuleCoupon::query()->where('code', $result->coupon)->sole();
    expect((int) $coupon->usage_limit)->toBe(1);

    $campaign->save(array_replace($settings, ['event' => 'nowruz']));
    expect($campaign->spin($run, $customer))->toBeFalse();
});

it('renders the active event on the homepage and serves its packaged art', function () {
    $campaign = app(StoreCampaign::class);
    $campaign->save(['event' => 'yalda', 'title' => 'جشن چرم شب یلدا', 'wheel' => false]);

    $this->get(route('shop.home.index'))
        ->assertOk()
        ->assertSee('جشن چرم شب یلدا')
        ->assertSee(route('shop.berkay.campaign.artwork', ['event' => 'yalda'], false));

    $this->get(route('shop.berkay.campaign.artwork', ['event' => 'yalda']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});
