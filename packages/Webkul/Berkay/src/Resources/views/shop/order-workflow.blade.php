@php $progress = app(\Webkul\Berkay\Services\OrderWorkflow::class)->timeline($order); @endphp
<section class="bk-order-workflow">
    <h2>{{ trans('berkay::workflow.manage') }}</h2>
    @include('berkay::shop.order-steps')
    @if ($progress['ended'])
        <p>{{ trans('berkay::workflow.closed') }}</p>
    @elseif ($progress['waiting'])
        <p>{{ trans('berkay::workflow.waiting') }}</p>
    @elseif ($progress['record']?->review === 'approved')
        <p>{{ trans('berkay::workflow.approved') }}</p>
        @if (! $progress['paid'] && $order->payment?->method === 'berkay_zarinpal')
            <form method="post" action="{{ route('shop.berkay.zarinpal.approved', $order->id) }}">
                @csrf
                <button class="bk-button" type="submit">{{ trans('berkay::workflow.pay') }}</button>
            </form>
        @elseif (! $progress['paid'])
            <p>{{ trans('berkay::workflow.offline') }}</p>
        @endif
    @else
        <p>{{ trans('berkay::workflow.autoApproved') }}</p>
    @endif

    @foreach ($order->shipments as $shipment)
        @if ($shipment->track_number)
            <p>{{ trans('berkay::workflow.tracking') }} <bdi dir="ltr">{{ $shipment->track_number }}</bdi></p>
        @endif
    @endforeach
</section>
