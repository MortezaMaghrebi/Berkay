<?php

use Webkul\Berkay\Services\StoreCampaign;

// ==== Wheel Prize Limits ====

it('saves a large discount cap and a prize with no cap', function () {
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.campaigns', 'berkay.campaigns.edit']);
    core()->getCurrentChannel()->base_currency->code = 'IRT';

    $url = route('admin.berkay.campaigns.update');
    $payload = [
        'event' => 'yalda',
        'wheel' => true,
        'days' => 7,
        'prizes' => [
            ['label' => 'Capped prize', 'type' => 'percent', 'value' => 10, 'weight' => 1, 'max_purchase' => '900000000'],
            ['label' => 'Uncapped prize', 'type' => 'percent', 'value' => 20, 'weight' => 1, 'max_purchase' => ''],
        ],
    ];

    $this->put($url, $payload)->assertRedirect(route('admin.berkay.campaigns.index'))->assertSessionHasNoErrors();

    $prizes = app(StoreCampaign::class)->settings()['prizes'];
    expect($prizes[0]['max_purchase'])->toBe('900000000')
        ->and(blank($prizes[1]['max_purchase']))->toBeTrue();
    $this->get(route('admin.berkay.campaigns.index'))->assertOk()->assertSee('900,000,000');

    $payload['prizes'][0]['max_purchase'] = '-1';
    $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('prizes.0.max_purchase');
});
