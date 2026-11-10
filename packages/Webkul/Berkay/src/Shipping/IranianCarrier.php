<?php

namespace Webkul\Berkay\Shipping;

use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\CartShippingRate;
use Webkul\Shipping\Carriers\AbstractShipping;

abstract class IranianCarrier extends AbstractShipping
{
    public function getTitle(): string
    {
        return trans('berkay::delivery.'.$this->getCode());
    }

    public function getDescription(): string
    {
        return $this->getConfigData('charge_mode') === 'fixed'
            ? trans('berkay::delivery.fixed-help')
            : trans('berkay::delivery.collect-help');
    }

    public function calculate(): CartShippingRate|false
    {
        if (! $this->isAvailable() || ! $cart = Cart::getCart()) {
            return false;
        }

        $subtotal = $cart->items->sum(fn ($item) => (float) $item->base_total - (float) $item->base_discount_amount);
        $threshold = (float) $this->getConfigData('free_threshold');
        $charge = $this->getConfigData('charge_mode') === 'fixed' ? 'fixed' : 'collect';
        $price = $charge === 'fixed' ? (float) $this->getConfigData('fixed_price') : 0;

        if (($threshold > 0 && $subtotal >= $threshold) || ($charge === 'fixed' && $price === 0.0)) {
            $charge = 'free';
            $price = 0;
        }

        return new CartShippingRate([
            'carrier' => $this->getCode(),
            'carrier_title' => $this->getTitle(),
            'method' => $this->getMethod(),
            'method_title' => $this->getTitle(),
            'method_description' => trans('berkay::delivery.'.$charge.'-help'),
            'charge_mode' => $charge,
            'base_price' => $price,
            'price' => core()->convertPrice($price),
        ]);
    }
}
