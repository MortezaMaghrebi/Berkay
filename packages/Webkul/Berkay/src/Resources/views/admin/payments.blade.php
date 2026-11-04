<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.payments') }}</x-slot>
    <section class="bk-admin">
        <h1>{{ trans('berkay::app.payments') }}</h1>
        <p>{{ trans('berkay::app.payment-review-info') }}</p>
        <x-admin::datagrid :src="route('admin.berkay.payments.index')" />
    </section>
</x-admin::layouts>
