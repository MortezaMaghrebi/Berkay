<aside class="bk-payment-notice" data-payment-notice="{{ route('shop.berkay.payment.notice') }}" hidden aria-live="polite">
    <div>
        <strong>{{ trans('shop::app.customers.account.orders.status.pending-payment') }}</strong>
        <a data-notice-order></a>
    </div>
    <button type="button" data-notice-dismiss aria-label="{{ trans('berkay::app.variant-dialog.close') }}">×</button>
</aside>
<script type="module" src="{{ asset('berkay/order-payment.js') }}?v={{ filemtime(public_path('berkay/order-payment.js')) }}"></script>
