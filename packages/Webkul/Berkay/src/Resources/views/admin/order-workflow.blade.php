@php $progress = app(\Webkul\Berkay\Services\OrderWorkflow::class)->timeline($order); @endphp
<section class="bk-order-workflow">
    <h2>{{ trans('berkay::workflow.manage') }}</h2>
    @include('berkay::shop.order-steps')
    @if ($progress['ended'])
        <p>{{ trans('berkay::workflow.closed') }}</p>
    @elseif (bouncer()->hasPermission('berkay.workshop'))
        @php
            $action = match (true) {
                $progress['waiting'] => 'approve',
                $progress['paid'] && ! $progress['done']['building'] && ! $progress['hasShipment'] => 'building',
                $progress['paid'] && $progress['shipped'] && ! $progress['done']['delivered'] => 'delivered',
                default => null,
            };
        @endphp
        @if ($action)
            <form method="post" action="{{ route('admin.berkay.workshop.advance', [$order->id, $action]) }}">
                @csrf
                <button class="primary-button" type="submit">{{ trans('berkay::workflow.'.match ($action) {
                    'approve' => 'approve', 'building' => 'begin', default => 'deliver',
                }) }}</button>
            </form>
        @endif
    @endif
</section>
