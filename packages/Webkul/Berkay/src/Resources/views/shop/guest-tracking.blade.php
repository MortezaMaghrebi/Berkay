@push('meta')
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
@endpush
<x-shop::layouts :has-feature="false">
    <x-slot:title>{{ trans('berkay::workflow.order', ['number' => $order->increment_id]) }}</x-slot>
    <div class="bk-section">
        <h1>{{ trans('berkay::workflow.order', ['number' => $order->increment_id]) }}</h1>
        @include('berkay::shop.order-workflow')
    </div>
</x-shop::layouts>
