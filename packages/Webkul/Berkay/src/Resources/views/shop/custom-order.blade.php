<x-shop::layouts>
    <x-slot:title>{{ trans('berkay::app.custom-orders') }}</x-slot>
    <section class="bk-section bk-reading">
        <h1>{{ trans('berkay::app.custom-title') }}</h1>
        <p class="bk-lead">{{ trans('berkay::app.request-info') }}</p>
        @if (session('success'))
            <p class="bk-notice" role="status">{{ session('success') }}</p>
        @endif
        <form method="post" action="{{ route('shop.berkay.custom_order.store') }}" class="bk-form">
            @csrf
            @foreach (['name', 'phone', 'product_type'] as $field)
                <label for="{{ $field }}">{{ trans('berkay::app.'.str_replace('_', '-', $field)) }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required maxlength="{{ $field === 'phone' ? 20 : 120 }}" @if ($field === 'phone') inputmode="tel" autocomplete="tel" dir="ltr" @endif>
                @error($field)<p class="bk-error">{{ $message }}</p>@enderror
            @endforeach
            <label for="description">{{ trans('berkay::app.description') }}</label>
            <textarea id="description" name="description" rows="6" required minlength="15" maxlength="4000">{{ old('description') }}</textarea>
            @error('description')<p class="bk-error">{{ $message }}</p>@enderror
            <div hidden><label for="website">Website</label><input name="website" id="website" tabindex="-1" autocomplete="off"></div>
            <button class="bk-button" type="submit">{{ trans('berkay::app.send') }}</button>
        </form>
    </section>
</x-shop::layouts>
