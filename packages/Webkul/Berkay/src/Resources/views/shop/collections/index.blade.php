@php $title = trans('berkay::collections.'.$collection); @endphp
<x-shop::layouts>
    <x-slot:title>{{ $title }}</x-slot>
    <section class="bk-collection-list">
        <a href="{{ route('shop.home.index') }}">{{ trans('berkay::collections.back-home') }}</a>
        <h1>{{ $title }}</h1>
        @if ($products->isEmpty())
            <p class="bk-collection-empty">{{ trans('berkay::collections.empty-'.$collection) }}</p>
        @else
            <ul class="bk-collection-grid">
                @foreach ($products as $product)
                    <li>@include('berkay::shop.collections.card')</li>
                @endforeach
            </ul>
            {{ $products->links() }}
        @endif
    </section>
</x-shop::layouts>
