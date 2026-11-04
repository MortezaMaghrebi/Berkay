<x-shop::layouts>
    <x-slot:title>{{ trans('berkay::app.payment-not-completed') }}</x-slot>
    @push('meta')<meta name="referrer" content="no-referrer">@endpush
    <section class="bk-section bk-reading">
        <h1>{{ trans('berkay::app.payment-not-completed') }}</h1>
        <p class="bk-lead">{{ trans('berkay::app.payment-pending-info') }}</p>
        @if ($canRetry)
            <a class="bk-button" rel="noreferrer" href="{{ route('shop.berkay.zarinpal.resume', $token) }}">{{ trans('berkay::app.retry-payment') }}</a>
        @endif
    </section>
</x-shop::layouts>
