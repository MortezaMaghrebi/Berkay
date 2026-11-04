<?php

namespace Webkul\Berkay\Repositories;

use Webkul\Berkay\Contracts\IntegrationSetting;
use Webkul\Core\Eloquent\Repository;

class IntegrationSettingRepository extends Repository
{
    /** Resolve the replaceable encrypted-setting contract. */
    public function model(): string
    {
        return IntegrationSetting::class;
    }
}
