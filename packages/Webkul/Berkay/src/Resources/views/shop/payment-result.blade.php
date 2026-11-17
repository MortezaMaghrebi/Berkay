<x-shop::layouts>
    <x-slot:title>{{ trans($paid ? 'berkay::workflow.paid' : 'berkay::app.payment-not-completed') }}</x-slot>
    @push('meta')<meta name="referrer" content="no-referrer">@endpush
    <section class="bk-payment-result" @if ($paid) data-paid @endif>
        <span aria-hidden="true">{{ $paid ? '✓' : '!' }}</span>
        <h1>{{ trans($paid ? 'berkay::workflow.paid' : 'berkay::app.payment-not-completed') }}</h1>
        @if ($paid || $canRetry)
            <p>{{ trans($paid ? 'berkay::app.payment-completed' : 'berkay::app.payment-pending-info') }}</p>
        @endif
        <div>
            @if ($canRetry)
                <a class="bk-button" rel="noreferrer" href="{{ route('shop.berkay.zarinpal.resume', $token) }}">{{ trans('berkay::app.retry-payment') }}</a>
            @endif
            @if ($orderUrl)
                <a class="bk-outline-button" href="{{ $orderUrl }}">{{ trans('berkay::workflow.manage') }}</a>
            @elseif (! $paid)
                <a class="bk-outline-button" href="{{ route('shop.checkout.cart.index') }}">{{ trans('shop::app.checkout.cart.index.shopping-cart') }}</a>
            @else
                <a class="bk-outline-button" href="{{ route('shop.home.index') }}">{{ trans('shop::app.checkout.cart.index.continue-shopping') }}</a>
            @endif
        </div>
    </section>
</x-shop::layouts>
