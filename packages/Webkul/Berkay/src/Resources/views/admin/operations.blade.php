<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.seller.home') }}</x-slot>
    <section class="bk-admin bk-seller-home">
        <p class="bk-eyebrow">{{ trans('berkay::app.seller.your-store') }}</p>
        <h1>{{ trans('berkay::app.seller.welcome') }}</h1>
        <p class="bk-help">{{ trans('berkay::app.seller.welcome-help') }}</p>
        <div class="bk-daily-actions">
            @foreach ([['catalog.products.create','admin.berkay.simple_product.create','new-product','new-product-help'],['sales.orders','admin.sales.orders.index','orders','orders-help']] as [$permission,$route,$label,$help])
                @if(bouncer()->hasPermission($permission))<a href="{{ route($route) }}"><h2>{{ trans('berkay::app.seller.'.$label) }}</h2><p>{{ trans('berkay::app.seller.'.$help) }}</p><span aria-hidden="true">←</span></a>@endif
            @endforeach
        </div>
        <h2>{{ trans('berkay::app.seller.everyday') }}</h2>
        <div class="bk-work-list">
            @foreach ([['catalog.products','admin.berkay.simple_product.index','products'],['catalog.categories','admin.berkay.simple_category.index','categories'],['berkay.articles','admin.berkay.articles.index','magazine'],['berkay.custom-orders','admin.berkay.custom_orders.index','custom-orders'],['berkay.integrations','admin.berkay.integrations.index','connections']] as [$permission,$route,$label])
                @if(bouncer()->hasPermission($permission))<a href="{{ route($route) }}">{{ trans('berkay::app.seller.'.$label) }} <span aria-hidden="true">←</span></a>@endif
            @endforeach
        </div>
        <details class="bk-advanced"><summary>{{ trans('berkay::app.seller.selling-guide') }}</summary><ol><li>{{ trans('berkay::app.seller.guide-product') }}</li><li>{{ trans('berkay::app.seller.guide-order') }}</li><li>{{ trans('berkay::app.seller.guide-shipping') }}</li></ol><p>{{ trans('berkay::app.launch-note') }}</p></details>
    </section>
</x-admin::layouts>
