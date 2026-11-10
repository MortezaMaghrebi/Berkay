<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::table.title') }}</x-slot>
    <section class="bk-admin bk-catalog-table"
        data-update="{{ route('admin.berkay.catalog_table.update') }}"
        data-adjust="{{ route('admin.berkay.catalog_table.adjust') }}"
        data-remove="{{ route('admin.berkay.catalog_table.destroy', ['parent' => 0, 'item' => 0]) }}"
        data-csrf="{{ csrf_token() }}"
        data-confirm-delete="{{ trans('berkay::table.confirm-delete') }}"
        data-confirm-adjust="{{ trans('berkay::table.confirm-adjust') }}"
        data-confirm-convert="{{ trans('berkay::table.confirm-convert') }}"
        data-unsaved="{{ trans('berkay::table.unsaved') }}"
        data-error="{{ trans('berkay::table.error') }}">
        <header class="bk-toolbar">
            <div>
                <h1>{{ trans('berkay::table.title') }}</h1>
                <p class="bk-help">{{ trans('berkay::table.intro') }}</p>
            </div>
            <a href="{{ route('admin.berkay.simple_product.index') }}">{{ trans('berkay::app.seller.products') }}</a>
        </header>
        <div class="bk-catalog-toolbar">
            <form method="get">
                <label for="bk-catalog-search">{{ trans('berkay::table.search') }}</label>
                <input id="bk-catalog-search" name="q" value="{{ request('q') }}" maxlength="100">
                <button>{{ trans('berkay::table.search') }}</button>
            </form>
            <button type="button" class="bk-button" data-save-all disabled>{{ trans('berkay::table.save-all') }}</button>
        </div>
        <details class="bk-form-section">
            <summary>{{ trans('berkay::table.mass-price') }}</summary>
            <form data-adjust-form class="bk-catalog-toolbar">
                <label>{{ trans('berkay::table.scope') }}
                    <select name="scope"><option value="all">{{ trans('berkay::table.all') }}</option><option value="products">{{ trans('berkay::app.seller.products') }}</option><option value="variants">{{ trans('berkay::table.variants') }}</option></select>
                </label>
                <label>{{ trans('berkay::table.method') }}
                    <select name="mode"><option value="percent">{{ trans('berkay::table.percent') }}</option><option value="fixed">{{ trans('berkay::table.fixed') }}</option></select>
                </label>
                <label>{{ trans('berkay::table.delta') }} <input name="value" type="number" step=".01" required placeholder="-5"></label>
                <button type="submit">{{ trans('berkay::table.apply') }}</button>
            </form>
        </details>
        <p class="bk-catalog-feedback" role="status" aria-live="polite"></p>

        <div class="bk-catalog-overflow" tabindex="0" aria-label="{{ trans('berkay::table.title') }}">
            <table>
                <thead><tr>
                    @foreach (['name','price','quantity','discount','status','actions'] as $column)
                        <th scope="col">{{ trans('berkay::table.'.$column) }}</th>
                    @endforeach
                </tr></thead>
                @foreach ($products as $product)
                    @php
                        $canAddColor = $color
                            && $product->attribute_family->custom_attributes->contains('id', $color->id)
                            && ($product->type === 'simple' || ($product->type === 'configurable' && $product->super_attributes->modelKeys() === [$color->id]));
                    @endphp
                    <tbody data-product="{{ $product->id }}" data-type="{{ $product->type }}">
                        @include('berkay::admin.catalog-table-row', ['item' => $product, 'parentRow' => true])
                        @foreach ($product->variants as $variant)
                            @include('berkay::admin.catalog-table-row', ['item' => $variant, 'parentRow' => false])
                        @endforeach
                        @if ($canAddColor)
                            <tr data-palette hidden>
                                <td colspan="6">
                                    <div class="bk-catalog-swatches">
                                        <span>{{ trans('berkay::table.add-color') }}</span>
                                        @foreach ($color->options as $option)
                                            @php $swatch = preg_match('/^#[0-9a-f]{6}$/i', $option->swatch_value ?? '') ? $option->swatch_value : '#a8a29e'; @endphp
                                            <button type="button" data-color="{{ $option->id }}" data-label="{{ $option->label ?: $option->admin_name }}" style="--swatch:{{ $swatch }}" title="{{ $option->label ?: $option->admin_name }}" aria-label="{{ $option->label ?: $option->admin_name }}"></button>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @endforeach
                <tbody data-variant-template hidden>
                    @include('berkay::admin.catalog-table-row', ['item' => null, 'parentRow' => false, 'canAddColor' => false])
                </tbody>
            </table>
        </div>
        {{ $products->links() }}

    </section>
    @pushOnce('scripts')
        <script type="module" src="{{ asset('berkay/catalog-table.js') }}?v={{ filemtime(public_path('berkay/catalog-table.js')) }}"></script>
    @endPushOnce
</x-admin::layouts>
