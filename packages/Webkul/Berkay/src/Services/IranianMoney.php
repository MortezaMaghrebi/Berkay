<?php

namespace Webkul\Berkay\Services;

use InvalidArgumentException;

class IranianMoney
{
    /** Convert the charged amount to whole rials without losing rial precision. */
    public function toRial(string|float|int $amount, string $currency): int
    {
        $this->toToman($amount, $currency);

        return (int) round((float) $amount * ($currency === 'IRT' ? 10 : 1), 0, PHP_ROUND_HALF_UP);
    }

    /** Convert a base-currency amount to whole tomans without foreign-currency guesses. */
    public function toToman(string|float|int $amount, string $currency): int
    {
        if (! in_array($currency, ['IRR', 'IRT'], true)) {
            throw new InvalidArgumentException('An Iranian base currency is required.');
        }

        if (
            ! is_numeric($amount)
            || (float) $amount < 0
            || ! is_finite((float) $amount)
            || (float) $amount > PHP_INT_MAX / 10
        ) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        return (int) round((float) $amount / ($currency === 'IRR' ? 10 : 1), 0, PHP_ROUND_HALF_UP);
    }
}
