<?php

namespace Webkul\Berkay\Providers;

use Konekt\Concord\BaseModuleServiceProvider;
use Webkul\Berkay\Models\Article;
use Webkul\Berkay\Models\CustomRequest;
use Webkul\Berkay\Models\IntegrationSetting;
use Webkul\Berkay\Models\PaymentAttempt;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /** Register replaceable Berkay entities. */
    protected $models = [
        PaymentAttempt::class,
        IntegrationSetting::class,
        Article::class,
        CustomRequest::class,
    ];
}
