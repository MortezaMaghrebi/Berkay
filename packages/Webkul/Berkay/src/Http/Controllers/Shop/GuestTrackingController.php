<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\View\View;
use Webkul\Sales\Models\Order;

class GuestTrackingController
{
    public function __invoke(int $id): View
    {
        $order = Order::query()->where('channel_id', core()->getCurrentChannel()->id)
            ->where('is_guest', 1)->findOrFail($id);

        return view('berkay::shop.guest-tracking', compact('order'));
    }
}
