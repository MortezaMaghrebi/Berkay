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
    @if (core()->getConfigData('berkay.store.identity.show_enamad'))
        <div>
            <a href="https://trustseal.enamad.ir/?id=8704950&amp;Code=3NOq7Gml8oKPeSHYbInf84L74Uu4Tvru" target="_blank" rel="noopener" referrerpolicy="origin" class="inline-flex rounded-lg bg-white p-2">
                <img src="https://trustseal.enamad.ir/logo.aspx?id=8704950&amp;Code=3NOq7Gml8oKPeSHYbInf84L74Uu4Tvru"
                    alt="eNAMAD" width="100" height="100" loading="lazy" referrerpolicy="origin" code="3NOq7Gml8oKPeSHYbInf84L74Uu4Tvru">
            </a>
        </div>
    @endif
</footer>
