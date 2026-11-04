<footer class="bk-footer">
    <div><a class="bk-footer-brand" href="{{ route('shop.home.index') }}">{{ core()->getConfigData('berkay.store.identity.name') ?: trans('berkay::app.brand') }}</a><p>{{ trans('berkay::app.footer-text') }}</p></div>
    <nav aria-label="{{ trans('berkay::app.catalog') }}">
        <a href="{{ route('shop.search.index') }}">{{ trans('berkay::app.all-products') }}</a>
        <a href="{{ route('shop.berkay.magazine.index') }}">{{ trans('berkay::app.magazine') }}</a>
        <a href="{{ route('shop.home.contact_us') }}">{{ trans('berkay::app.contact') }}</a>
    </nav>
    <div><h2>{{ trans('berkay::app.support') }}</h2>
        @if ($phone = core()->getConfigData('berkay.store.identity.support_phone'))
            <a dir="ltr" href="tel:{{ preg_replace('/[^+0-9]/', '', $phone) }}">{{ $phone }}</a>
        @endif
        <a href="{{ route('shop.customer.session.create') }}">{{ trans('berkay::app.account') }}</a>
    </div>
</footer>
