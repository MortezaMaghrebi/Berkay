<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.variant-settings.title') }}</x-slot>
    <section class="bk-admin bk-form bk-attribute-settings">
        <h1>{{ trans('berkay::app.variant-settings.title') }}</h1>
        <p class="bk-help">{{ trans('berkay::app.variant-settings.simple-help') }}</p>
        @include('berkay::admin.seller-feedback')
        @foreach (['colors' => $color, 'materials' => $material] as $label => $attribute)
            @if ($attribute)
                <section class="bk-form-section">
                    <h2>{{ trans('berkay::app.variant-settings.'.$label) }}</h2>
                    @if (bouncer()->hasPermission('catalog.attributes.edit'))
                        @include('berkay::admin.attribute-option-row', ['option' => null])
                    @endif
                    @foreach ($attribute->options as $option)
                        @include('berkay::admin.attribute-option-row')
                    @endforeach
                </section>
            @endif
        @endforeach
        <p class="bk-help">{{ trans('berkay::app.variant-settings.usage') }}</p>
    </section>
</x-admin::layouts>

@pushOnce('scripts')
    <script src="{{ asset('berkay/attribute-settings.js') }}?v={{ filemtime(public_path('berkay/attribute-settings.js')) }}" defer></script>
@endPushOnce
