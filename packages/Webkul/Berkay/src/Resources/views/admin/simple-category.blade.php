<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.seller.category-title') }}</x-slot>
    <section class="bk-admin bk-seller-editor">
        <div class="bk-toolbar"><h1>{{ trans('berkay::app.seller.category-title') }}</h1><a href="{{ route('admin.berkay.simple_category.index') }}">{{ trans('berkay::app.back') }}</a></div>
        <p class="bk-help">{{ trans('berkay::app.seller.category-intro') }}</p>
        @include('berkay::admin.seller-feedback')
        <form class="bk-form" method="post" enctype="multipart/form-data" action="{{ $category ? route('admin.berkay.simple_category.update', $category->id) : route('admin.berkay.simple_category.store') }}">
            @csrf
            @if ($category) @method('PUT') @endif
            <label for="name">{{ trans('berkay::app.seller.category-name') }}</label>
            <input id="name" name="name" value="{{ old('name', $category?->name) }}" required maxlength="150">
            <label for="parent_id">{{ trans('berkay::app.seller.parent-category') }}</label>
            <select id="parent_id" name="parent_id">
                <option value="">{{ trans('berkay::app.seller.root-category') }}</option>
                @foreach ($categories as $node)
                    @include('berkay::admin.category-options', ['node' => $node, 'depth' => 0, 'selected' => old('parent_id', $category?->parent_id), 'excluded' => $category?->id])
                @endforeach
            </select>
            <small>{{ trans('berkay::app.seller.parent-help') }}</small>
            @if ($category?->logo_url)<img class="bk-category-preview" src="{{ $category->logo_url }}" alt="{{ $category->name }}" width="90" height="90">@endif
            <label for="category_logo">{{ trans('berkay::app.seller.category-photo') }}</label>
            <input id="category_logo" name="category_logo" type="file" accept="image/jpeg,image/png,image/webp" data-bk-category-photo>
            @include('berkay::admin.product-photo-crop', ['categoryPhoto' => true])
            <label for="status">{{ trans('berkay::app.seller.status') }}</label>
            <select id="status" name="status">
                <option value="1" @selected((int) old('status', $category?->status ?? 1) === 1)>{{ trans('berkay::app.seller.visible') }}</option>
                <option value="0" @selected((int) old('status', $category?->status ?? 1) === 0)>{{ trans('berkay::app.seller.hidden') }}</option>
            </select>
            <p class="bk-help">{{ trans('berkay::app.seller.category-automatic') }}</p>
            <button class="bk-button" type="submit">{{ trans('berkay::app.seller.save-category') }}</button>
        </form>
    </section>
</x-admin::layouts>
