<?php

use Illuminate\Support\Facades\DB;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Checkout\Models\Cart;

it('saves the merchandise review limit in tomans from the workshop settings', function () {
    $this->loginAsAdmin();
    $section = ['berkay', 'workshop'];
    $field = 'berkay.workshop.review.amount_limit';
    $channel = core()->getDefaultChannel()->code;

    $this->get(route('admin.configuration.index', $section))
        ->assertOk()->assertSee('name="berkay[workshop][review][amount_limit]"', false)
        ->assertSee('label="'.trans('berkay::workflow.amount').'"', false);

    $this->postJson(route('admin.configuration.store', $section), [
        'channel' => $channel,
        'berkay' => ['workshop' => ['review' => ['amount_limit' => 175_000_000]]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('core_config', ['code' => $field, 'channel_code' => $channel, 'value' => '175000000']);

    $this->postJson(route('admin.configuration.store', $section), [
        'channel' => $channel,
        'berkay' => ['workshop' => ['review' => ['amount_limit' => 0]]],
    ])->assertUnprocessable()->assertJsonValidationErrorFor($field);

    $cart = new Cart(['base_currency_code' => 'IRT', 'base_sub_total' => 150_000_000]);
    $cart->setRelation('items', collect());
    expect(app(OrderWorkflow::class)->requiresReview($cart))->toBeFalse();
});

it('lets authorized staff release a waiting order from the workshop queue', function () {
    $this->loginAsAdmin();
    $order = $this->createOrder(items: [['price' => 30_000_000, 'qty_ordered' => 4]]);
    DB::table('berkay_order_workflows')->insert(['order_id' => $order->id, 'review' => 'waiting']);

    $this->get(route('admin.berkay.workshop.index'))->assertOk()
        ->assertSee(route('admin.sales.orders.view', $order->id));

    $this->post(route('admin.berkay.workshop.advance', [$order->id, 'approve']))
        ->assertSessionHas('success');
    expect(app(OrderWorkflow::class)->waiting($order))->toBeFalse();
});
