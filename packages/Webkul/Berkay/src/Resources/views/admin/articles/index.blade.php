<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.magazine') }}</x-slot>
    <section class="bk-admin">
        <div class="bk-toolbar">
            <h1>{{ trans('berkay::app.magazine') }}</h1>
            @if (bouncer()->hasPermission('berkay.articles.create'))
                <a class="bk-button" href="{{ route('admin.berkay.articles.create') }}">{{ trans('berkay::app.create') }}</a>
            @endif
        </div>
        <x-admin::datagrid :src="route('admin.berkay.articles.index')" />
    </section>
</x-admin::layouts>
