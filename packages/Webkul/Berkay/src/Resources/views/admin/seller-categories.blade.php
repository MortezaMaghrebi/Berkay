<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.seller.categories') }}</x-slot>
    <section class="bk-admin">
        <div class="bk-toolbar"><h1>{{ trans('berkay::app.seller.categories') }}</h1>@if (bouncer()->hasPermission('catalog.categories.create'))<a class="bk-button" href="{{ route('admin.berkay.simple_category.create') }}">＋ {{ trans('berkay::app.seller.new-category') }}</a>@endif</div>
        <p class="bk-help">{{ trans('berkay::app.seller.category-intro') }}</p>
        @include('berkay::admin.seller-feedback')
        <div class="bk-category-tree">
            @forelse ($categories as $node)
                @include('berkay::admin.category-row', ['node' => $node])
            @empty
                <p>{{ trans('berkay::app.seller.no-categories') }}</p>
            @endforelse
        </div>
    </section>
</x-admin::layouts>
