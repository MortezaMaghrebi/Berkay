@php
    $attempt = app(\Webkul\Berkay\Repositories\PaymentAttemptRepository::class)->findOneWhere(['order_id' => $order->id]);
    $methods = $attempt ? collect() : collect(\Webkul\Payment\Facades\Payment::getPaymentMethods());
    $methods = $methods->filter(fn ($method) => $method['method'] === 'moneytransfer'
        || isset(\Webkul\Berkay\Services\BankGateways::METHODS[\Illuminate\Support\Str::after($method['method'], 'berkay_')]))->values();
    $selected = $methods->contains('method', $order->payment->method) ? $order->payment->method : $methods->first()['method'] ?? null;
@endphp

@if ($attempt || $methods->isNotEmpty())
    <button class="bk-button" type="button" data-open-payment="bk-order-payment-{{ $order->id }}" aria-haspopup="dialog">{{ trans('berkay::workflow.pay') }}</button>
    <dialog id="bk-order-payment-{{ $order->id }}" class="bk-payment-dialog" aria-labelledby="bk-payment-title-{{ $order->id }}">
        <header>
            <h2 id="bk-payment-title-{{ $order->id }}">{{ trans('berkay::workflow.pay') }}</h2>
            <form method="dialog">
                <button type="submit" aria-label="{{ trans('berkay::app.variant-dialog.close') }}">×</button>
            </form>
        </header>
        <form method="post" action="{{ route('shop.berkay.zarinpal.approved', $order->id) }}" class="bk-approved-payment">
            @csrf
            @unless ($attempt)
                <fieldset class="bk-checkout-address-list">
                    <legend>{{ trans('shop::app.customers.account.orders.view.payment-method') }}</legend>
                    @foreach ($methods as $method)
                        <label class="bk-checkout-method">
                            <input type="radio" name="method" value="{{ $method['method'] }}" @checked($method['method'] === $selected) required>
                            <span>{{ $method['method_title'] }}</span>
                            @if ($method['image'])
                                <img src="{{ $method['image'] }}" width="36" height="36" alt="">
                            @endif
                        </label>
                    @endforeach
                </fieldset>
            @endunless
            @if ($attempt || $order->payment->method === 'moneytransfer')
                <p>{{ trans('berkay::app.payment-pending-info') }}</p>
            @endif
            <button class="bk-button" type="submit">{{ trans('berkay::workflow.pay') }}</button>
        </form>
    </dialog>
@else
    <p>{{ trans('berkay::workflow.gatewayDisabled') }}</p>
@endif
