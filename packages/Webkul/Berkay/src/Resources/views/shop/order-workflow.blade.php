@php $progress = app(\Webkul\Berkay\Services\OrderWorkflow::class)->timeline($order); @endphp
@php $provider = \Illuminate\Support\Str::after((string) $order->payment?->method, 'berkay_'); @endphp
<section class="bk-order-workflow">
    <h2>{{ trans('berkay::workflow.manage') }}</h2>
    @include('berkay::shop.order-steps')
    @if ($progress['ended'])
        <p>{{ trans('berkay::workflow.closed') }}</p>
    @elseif ($progress['waiting'])
        <p>{{ trans('berkay::workflow.waiting') }}</p>
    @elseif ($progress['record']?->review === 'approved')
        <p>{{ trans('berkay::workflow.approved') }}</p>
    @else
        <p>{{ trans('berkay::workflow.autoApproved') }}</p>
    @endif

    @if (
        ! $order->is_guest
        && (int) auth()->guard('customer')->id() === (int) $order->customer_id
        && ! $progress['ended']
        && ! $progress['waiting']
        && ! $progress['paid']
        && $order->canInvoice()
        && ! $order->invoices()->where('state', \Webkul\Sales\Models\Invoice::STATUS_PAID)->exists()
        && (isset(\Webkul\Berkay\Services\BankGateways::METHODS[$provider]) || $order->payment?->method === 'moneytransfer')
    )
        @include('berkay::shop.approved-payment-options')
    @endif

    @if (! $progress['ended'] && ! $progress['waiting'] && ! $progress['paid'] && $order->payment?->method === 'moneytransfer')
        @include('berkay::shop.transfer-instructions')
    @endif

    @foreach ($order->shipments as $shipment)
        @if ($shipment->track_number)
            <p>{{ trans('berkay::workflow.tracking') }} <bdi dir="ltr">{{ $shipment->track_number }}</bdi></p>
        @endif
    @endforeach
</section>
