<?php

namespace Webkul\Berkay\Shipping;

use Webkul\Checkout\Facades\Cart;
use Webkul\Shipping\Facades\Shipping;

class ShippingQuote
{
    /** Refresh the selected rate before accepting an order or redirecting to the bank. */
    public function refresh($cart): bool
    {
        if (! $cart || ! $cart->haveStockableItems() || ! $cart->shipping_address || ! $cart->shipping_method) {
            return true;
        }

        $before = $cart->selected_shipping_rate;
        Shipping::collectRates();
        Cart::collectTotals();
        $after = Cart::getCart()?->selected_shipping_rate;

        return $after && $before
            && (float) $after->base_price === (float) $before->base_price
            && $after->charge_mode === $before->charge_mode;
    }
}
