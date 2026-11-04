<?php

namespace Webkul\Berkay\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Berkay\Contracts\PaymentAttempt as PaymentAttemptContract;

class PaymentAttempt extends Model implements PaymentAttemptContract
{
    /** Store payment attempts separately from core order data. */
    protected $table = 'berkay_payment_attempts';

    /** Allow server-controlled payment fields. */
    protected $fillable = ['gateway_context', 'token', 'order_id', 'cart_id', 'provider', 'authority', 'amount_rial', 'status', 'receipt', 'verified_at'];

    /** Normalize monetary and verification values. */
    protected $casts = ['gateway_context' => 'encrypted:array', 'amount_rial' => 'integer', 'verified_at' => 'datetime'];

    /** Keep frozen provider credentials out of model serialization. */
    protected $hidden = ['gateway_context', 'token'];
}
