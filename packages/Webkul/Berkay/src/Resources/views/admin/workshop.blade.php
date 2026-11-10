<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::workflow.manage') }}</x-slot>
    <section class="bk-admin">
        <div class="bk-toolbar">
            <div><h1>{{ trans('berkay::workflow.manage') }}</h1><p class="bk-help">{{ trans('berkay::workflow.settingsHelp') }}</p></div>
            <div class="flex flex-wrap gap-2">
                @if (bouncer()->hasPermission('configuration'))
                    <a href="{{ route('admin.configuration.index', ['slug' => 'berkay', 'slug2' => 'workshop']) }}">{{ trans('berkay::workflow.settings') }}</a>
                    <a href="{{ route('admin.configuration.index', ['slug' => 'sales', 'slug2' => 'carriers']) }}">{{ trans('berkay::delivery.settings') }}</a>
                @endif
                <a href="{{ route('admin.sales.orders.index') }}">{{ trans('berkay::app.seller.orders') }}</a>
            </div>
        </div>
        @include('berkay::admin.seller-feedback')
        <h2>{{ trans('berkay::workflow.queue') }}</h2>
        @forelse ($orders as $order)
            <article class="bk-order-workflow bk-toolbar">
                <div>
                    <h3>{{ trans('berkay::workflow.order', ['number' => $order->increment_id]) }}</h3>
                    <p>{{ $order->customer_full_name }} · {{ trans('berkay::workflow.count', ['count' => $order->total_qty_ordered]) }} · {{ core()->formatBasePrice($order->base_grand_total) }}</p>
                </div>
                @if (bouncer()->hasPermission('sales.orders.view'))
                    <a class="bk-button" href="{{ route('admin.sales.orders.view', $order->id) }}">{{ trans('berkay::workflow.review') }}</a>
                @endif
            </article>
        @empty
            <p class="bk-notice">{{ trans('berkay::workflow.empty') }}</p>
        @endforelse
        {{ $orders->links() }}
    </section>
</x-admin::layouts>
