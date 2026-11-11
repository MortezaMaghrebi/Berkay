<div class="bk-category-row">
    <div class="flex-wrap"><strong>{{ $node->name }}</strong><small>{{ trans('berkay::app.seller.'.($node->status ? 'visible' : 'hidden')) }}</small>@if (bouncer()->hasPermission('catalog.categories.edit'))<a href="{{ route('admin.berkay.simple_category.edit', $node->id) }}">{{ trans('berkay::app.edit') }}</a>@endif
        @if (bouncer()->hasPermission('catalog.categories.delete'))
            <form
                method="post"
                action="{{ route('admin.berkay.simple_category.delete', $node->id) }}"
                data-confirm="{{ trans('berkay::category-delete.confirm', ['name' => $node->name]) }}"
                onsubmit="return window.confirm(this.dataset.confirm)"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="min-h-11 px-2 py-1 text-red-700 dark:text-red-400">{{ trans('berkay::app.delete') }}</button>
            </form>
        @endif
    </div>
    @foreach ($node->children as $child)
        @include('berkay::admin.category-row', ['node' => $child])
    @endforeach
</div>
