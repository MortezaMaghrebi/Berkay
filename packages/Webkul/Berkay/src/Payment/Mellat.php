<?php

namespace Webkul\Berkay\Payment;

class Mellat extends DirectBank
{
    /** Identify the checkout payment method. */
    protected $code = 'berkay_mellat';

    /** Identify the BehPardakht adapter. */
    protected string $provider = 'mellat';
}
