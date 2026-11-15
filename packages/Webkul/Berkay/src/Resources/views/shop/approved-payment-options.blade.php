@php
    $attempt = app(\Webkul\Berkay\Repositories\PaymentAttemptRepository::class)->findOneWhere(['order_id' => $order->id]);
    $methods = $attempt ? collect() : collect(\Webkul\Payment\Facades\Payment::getPaymentMethods());
    $selected = $methods->contains('method', $order->payment->method) ? $order->payment->method : $methods->first()['method'] ?? null;
@endphp

@if ($attempt || $methods->isNotEmpty())
    <form method="post" action="{{ route('shop.berkay.zarinpal.approved', $order->id) }}" class="bk-approved-payment">
        @csrf
        @unless ($attempt)
            <fieldset class="bk-checkout-address-list">
                <legend>روش پرداخت سفارش را انتخاب کنید</legend>
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
        <button class="bk-button" type="submit">{{ trans('berkay::workflow.pay') }}</button>
    </form>
@else
    <p>{{ trans('berkay::workflow.gatewayDisabled') }}</p>
@endif
