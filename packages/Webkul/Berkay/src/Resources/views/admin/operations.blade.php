<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.seller.home') }}</x-slot>
    <section class="bk-admin bk-seller-home">
        <div class="bk-dashboard-heading">
            <div><h1>{{ trans('berkay::app.seller.welcome') }}</h1><p class="bk-help">{{ trans('berkay::app.seller.welcome-help') }}</p></div>
            <a class="bk-outline-button" href="{{ route('shop.home.index') }}" target="_blank" rel="noopener">{{ trans('berkay::app.refresh.view-store') }} <span class="icon-store" aria-hidden="true"></span></a>
        </div>
        @if (bouncer()->hasPermission('sales.orders'))
            @php $orderCounts = app(\Webkul\Berkay\Repositories\SellerOverviewRepository::class)->orderCounts(); @endphp
            <div class="bk-order-overview" aria-label="{{ trans('berkay::app.refresh.order-overview') }}">
                @foreach (['pending' => 'waiting-payment', 'processing' => 'processing-orders', 'completed' => 'completed-orders'] as $status => $label)
                    <a href="{{ route('admin.sales.orders.index', ['filters' => ['status' => [$status]]]) }}"><span>{{ trans('berkay::app.refresh.'.$label) }}</span><strong>{{ (new \NumberFormatter(app()->getLocale(), \NumberFormatter::DECIMAL))->format($orderCounts[$status]) }}</strong></a>
                @endforeach
            </div>
        @endif
        <div class="bk-seller-workspace">
            <div>
                @if (bouncer()->hasPermission('catalog.products.create'))
                    <a class="bk-create-product-callout" href="{{ route('admin.berkay.simple_product.create') }}">
                        <div><h2>{{ trans('berkay::app.seller.new-product') }}</h2><p>{{ trans('berkay::app.seller.new-product-help') }}</p><span class="bk-callout-action">{{ trans('berkay::app.refresh.start-product') }}</span></div>
                        <svg viewBox="0 0 100 100" width="110" height="110" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="m22 35-5 49h66l-5-49zM35 38V24a15 15 0 0 1 30 0v14M28 35v10m44-10v10"/><path d="M40 61h20m-10-10v20" stroke-width="2"/></svg>
                    </a>
                @endif
                <section class="bk-work-panel">
                    <h2>{{ trans('berkay::app.seller.everyday') }}</h2>
                    <div class="bk-work-list">
                        @foreach ([['catalog.products','admin.berkay.simple_product.index','products','icon-product'],['sales.orders','admin.sales.orders.index','orders','icon-sales'],['catalog.categories','admin.berkay.simple_category.index','categories','icon-category'],['berkay.articles','admin.berkay.articles.index','magazine','icon-cms'],['berkay.custom-orders','admin.berkay.custom_orders.index','custom-orders','icon-sales'],['berkay.integrations','admin.berkay.integrations.index','connections','icon-settings']] as [$permission,$route,$label,$icon])
                            @if(bouncer()->hasPermission($permission))<a href="{{ route($route) }}"><span class="{{ $icon }}" aria-hidden="true"></span>{{ trans('berkay::app.seller.'.$label) }}<span class="bk-row-arrow" aria-hidden="true">‹</span></a>@endif
                        @endforeach
                    </div>
                </section>
            </div>
            <aside class="bk-selling-guide">
                <h2>{{ trans('berkay::app.seller.selling-guide') }}</h2>
                <ol>
                    <li><strong>{{ trans('berkay::app.refresh.step-product') }}</strong><p>{{ trans('berkay::app.seller.guide-product') }}</p></li>
                    <li><strong>{{ trans('berkay::app.refresh.step-payment') }}</strong><p>{{ trans('berkay::app.seller.guide-order') }}</p></li>
                    <li><strong>{{ trans('berkay::app.refresh.step-shipping') }}</strong><p>{{ trans('berkay::app.seller.guide-shipping') }}</p></li>
                </ol>
                <details class="bk-advanced"><summary>{{ trans('berkay::app.refresh.before-selling') }}</summary><p>{{ trans('berkay::app.launch-note') }}</p></details>
            </aside>
        </div>
    </section>
</x-admin::layouts>
