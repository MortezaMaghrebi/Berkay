<?php

namespace Webkul\Berkay\Support;

class ProductDiscount
{
    /** Convert a discount percentage to the sale price stored by Bagisto. */
    public static function price(float $price, float $percent): ?float
    {
        $sale = round($price * (1 - $percent / 100), 4);

        return $percent > 0 && $sale > 0 && $sale < $price ? $sale : null;
    }

    /** Show the percentage represented by an existing sale price. */
    public static function percent(float $price, float $sale): ?float
    {
        return $sale > 0 && $sale < $price ? round(100 * (1 - $sale / $price), 2) : null;
    }
}
