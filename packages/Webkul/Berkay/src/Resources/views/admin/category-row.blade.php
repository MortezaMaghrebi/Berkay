<div class="bk-category-row">
    <div><strong>{{ $node->name }}</strong><small>{{ trans('berkay::app.seller.'.($node->status ? 'visible' : 'hidden')) }}</small>@if (bouncer()->hasPermission('catalog.categories.edit'))<a href="{{ route('admin.berkay.simple_category.edit', $node->id) }}">{{ trans('berkay::app.edit') }}</a>@endif</div>
    @foreach ($node->children as $child)
        @include('berkay::admin.category-row', ['node' => $child])
    @endforeach
</div>
