<?php

use Webkul\CartRule\Helpers\CartRule;

it('limits campaign percentage discounts to the eligible part of the subtotal', function (?float $limit, array $totals, float $expected) {
    $rules = new class($limit, $totals) extends CartRule
    {
        /**
         * Set up the cart and campaign rule for the calculation.
         */
        public function __construct(?float $limit, array $totals)
        {
            $this->cart = (object) ['items' => collect($totals)->map(fn ($total) => ['base_total' => $total])];
            $this->campaignSubtotalLimits = [1 => $limit];
        }

        /**
         * Return the effective merchandise subtotal for a campaign rule.
         */
        public function eligibleSubtotal(): float
        {
            return $this->cart->items->sum('base_total') * $this->campaignDiscountRate((object) ['id' => 1]);
        }
    };

    expect($rules->eligibleSubtotal())->toBe($expected);
})->with([
    'above cap with multiple lines' => [100_000.0, [80_000, 120_000], 100_000.0],
    'at cap' => [100_000.0, [100_000], 100_000.0],
    'below cap' => [100_000.0, [80_000], 80_000.0],
    'uncapped' => [null, [80_000, 120_000], 200_000.0],
    'zero subtotal' => [100_000.0, [0], 0.0],
]);
