<?php

namespace Webkul\Berkay\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Berkay\Contracts\IntegrationSetting as IntegrationSettingContract;

class IntegrationSetting extends Model implements IntegrationSettingContract
{
    /** Keep provider credentials separate from public store configuration. */
    protected $table = 'berkay_integration_settings';

    /** Accept only server-selected setting names and values. */
    protected $fillable = ['name', 'value'];

    /** Encrypt configuration at rest using the application key. */
    protected $casts = ['value' => 'encrypted:array'];

    /** Prevent credentials from being exposed by model serialization. */
    protected $hidden = ['value'];
}
