<?php

namespace Webkul\Berkay\Payment;

class IranKish extends DirectBank
{
    /** Identify the checkout payment method. */
    protected $code = 'berkay_irankish';

    /** Identify the Iran Kish adapter for the Bank Tejarat terminal. */
    protected string $provider = 'irankish';
}
