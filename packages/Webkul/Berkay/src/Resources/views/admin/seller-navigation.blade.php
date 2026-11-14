<nav class="bk-seller-nav" aria-label="{{ trans('berkay::app.seller.store-menu') }}">
    @foreach ([
        ['berkay', 'admin.berkay.index', 'home', 'icon-dashboard'],
        ['catalog.products', 'admin.berkay.simple_product.index', 'products', 'icon-product'],
        ['sales.orders', 'admin.sales.orders.index', 'orders', 'icon-sales'],
        ['catalog.categories', 'admin.berkay.simple_category.index', 'categories', 'icon-category'],
        ['berkay.articles', 'admin.berkay.articles.index', 'magazine', 'icon-cms'],
        ['berkay.custom-orders', 'admin.berkay.custom_orders.index', 'custom-orders', 'icon-sales'],
        ['customers.customers', 'admin.customers.customers.index', 'customers', 'icon-customer'],
        ['berkay.integrations', 'admin.berkay.integrations.index', 'connections', 'icon-settings'],
    ] as [$permission, $route, $label, $icon])
        @if (bouncer()->hasPermission($permission))
            <a href="{{ route($route) }}" @class(['bk-nav-active' => request()->routeIs($route) || (str_contains($route, 'simple_product') && request()->routeIs('admin.berkay.simple_product.*')) || (str_contains($route, 'simple_category') && request()->routeIs('admin.berkay.simple_category.*'))])>
                <span class="{{ $icon }}" aria-hidden="true"></span>{{ trans('berkay::app.seller.'.$label) }}
            </a>
        @endif
    @endforeach
    @if (bouncer()->hasPermission('berkay.campaigns'))
        <a href="{{ route('admin.berkay.campaigns.index') }}" @class(['bk-nav-active' => request()->routeIs('admin.berkay.campaigns.*')])>
            <span class="icon-cms" aria-hidden="true"></span>رویدادهای فروشگاه
        </a>
    @endif
    @if (bouncer()->hasPermission('sales.orders'))
        <a href="{{ route('admin.berkay.workshop.index') }}" @class(['bk-nav-active' => request()->routeIs('admin.berkay.workshop.index')])>
            <span class="icon-sales" aria-hidden="true"></span>{{ trans('berkay::workflow.manage') }}
        </a>
    @endif
    @if (bouncer()->hasPermission('berkay.integrations'))
        <a href="{{ route('admin.berkay.kavenegar.index') }}" @class(['bk-nav-active' => request()->routeIs('admin.berkay.kavenegar.*')])>
            <span class="icon-settings" aria-hidden="true"></span>{{ trans('berkay::sms.panel') }}
        </a>
    @endif
    @if(auth()->guard('admin')->user()?->role?->permission_type === 'all')
        <a href="{{ route('admin.berkay.users.index') }}" @class(['bk-nav-active' => request()->routeIs('admin.berkay.users.*')])><span class="icon-customer" aria-hidden="true"></span>{{ trans('berkay::app.users.title') }}</a>
    @endif
    @if(bouncer()->hasPermission('catalog.attributes') && bouncer()->hasPermission('berkay.attributes'))
        <a href="{{ route('admin.berkay.attributes.index') }}">{{ trans('berkay::app.variant-settings.title') }}</a>
    @endif
    @if (bouncer()->hasPermission('configuration'))
        <a href="{{ route('admin.configuration.index', ['slug' => 'berkay', 'slug2' => 'leather']) }}" @class(['bk-nav-active' => request()->routeIs('admin.configuration.index') && request()->route('slug') === 'berkay' && request()->route('slug2') === 'leather'])>
            <span class="icon-cms" aria-hidden="true"></span>{{ trans('berkay::leather.title') }}
        </a>
        <a href="{{ route('admin.configuration.index', ['slug' => 'berkay', 'slug2' => 'home']) }}" @class(['bk-nav-active' => request()->routeIs('admin.configuration.index') && request()->route('slug') === 'berkay' && request()->route('slug2') === 'home'])>
            <span class="icon-settings" aria-hidden="true"></span>{{ trans('berkay::collections.settings') }}
        </a>
    @endif
    <details class="bk-nav-advanced" @if(request()->is(config('app.admin_url').'/configuration*')) open @endif>
        <summary>{{ trans('berkay::app.seller.advanced-settings') }}</summary>
        <p>{{ trans('berkay::app.seller.advanced-help') }}</p>
        @foreach ([
            ['configuration', 'admin.configuration.index', 'store-settings'],
            ['settings.roles', 'admin.settings.roles.index', 'staff-access'],
            ['reporting', 'admin.reporting.sales.index', 'reports'],
            ['marketing', 'admin.marketing.promotions.catalog_rules.index', 'promotions'],
            ['cms', 'admin.cms.index', 'pages'],
            ['berkay.payments', 'admin.berkay.payments.index', 'payments'],
        ] as [$permission, $route, $label])
            @if (bouncer()->hasPermission($permission) && Route::has($route))<a href="{{ route($route) }}">{{ trans('berkay::app.seller.'.$label) }}</a>@endif
        @endforeach
    </details>
</nav>
