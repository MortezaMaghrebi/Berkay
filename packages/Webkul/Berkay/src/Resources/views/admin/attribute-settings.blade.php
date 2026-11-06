<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.variant-settings.title') }}</x-slot>
    <section class="bk-admin bk-form">
        <h1>{{ trans('berkay::app.variant-settings.title') }}</h1>
        <p class="bk-help">{{ trans('berkay::app.variant-settings.help') }}</p>
        @foreach (['colors' => $color, 'materials' => $material] as $label => $attribute)
            <section class="bk-form-section">
                <h2>{{ trans('berkay::app.variant-settings.'.$label) }}</h2>
                @if ($attribute)
                    <div class="bk-admin-color-choices">
                        @foreach ($attribute->options as $option)
                            <span class="bk-attribute-chip">@if ($label === 'colors')<span class="bk-admin-color-dot" style="background-color:{{ preg_match('/^#[0-9a-fA-F]{6}$/', $option->swatch_value ?? '') ? $option->swatch_value : '#dddddd' }}" aria-hidden="true"></span>@endif{{ $option->admin_name }}</span>
                        @endforeach
                    </div>
                    @if (bouncer()->hasPermission('catalog.attributes.edit'))<a class="bk-button" href="{{ route('admin.catalog.attributes.edit', $attribute->id) }}">{{ trans('berkay::app.variant-settings.edit') }}</a>@endif
                @endif
            </section>
        @endforeach
        <a href="{{ route('admin.catalog.attributes.index') }}">{{ trans('berkay::app.variant-settings.all') }}</a>
    </section>
</x-admin::layouts>
