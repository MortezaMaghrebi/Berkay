<?php

namespace Webkul\Berkay\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Berkay\Contracts\CustomRequest as CustomRequestContract;

class CustomRequest extends Model implements CustomRequestContract
{
    /** Store records in the extension table. */
    protected $table = 'berkay_custom_requests';

    /** Allow only explicitly managed business fields. */
    protected $fillable = ['reference', 'name', 'phone', 'product_type', 'description', 'status', 'admin_notes'];
}
