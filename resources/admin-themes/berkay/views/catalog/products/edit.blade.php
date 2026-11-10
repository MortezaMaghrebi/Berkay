<x-admin::layouts>
    <x-slot:title>
        {{ $product->name ?: trans('admin::app.catalog.products.edit.title') }}
    </x-slot>

    <div class="bk-product-workspace" data-saved-label="{{ trans('berkay::app.product-editor.saved-state') }}" data-dirty-label="{{ trans('berkay::app.product-editor.dirty-state') }}">
    {!! view_render_event('bagisto.admin.catalog.product.edit.before', ['product' => $product]) !!}

    <x-admin::form
        method="PUT"
        enctype="multipart/form-data"
    >
        {!! view_render_event('bagisto.admin.catalog.product.edit.actions.before', ['product' => $product]) !!}

        <!-- Page Header -->
        <div class="bk-product-savebar grid gap-2.5">
            <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
                <div class="grid gap-1.5">
                    <p class="text-xl font-bold leading-6 text-gray-800 dark:text-white">
                        {{ $product->name ?: trans('admin::app.catalog.products.edit.title') }}
                    </p>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Back Button -->
                    <a
                        href="{{ route('admin.berkay.simple_product.index') }}"
                        class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                    >
                        {{ trans('berkay::app.seller.products') }}
                    </a>

                    <!-- Preview Button -->
                    @if (
                        $product->status
                        && $product->visible_individually
                        && $product->url_key
                    )
                        <a
                            href="{{ route('shop.product_or_category.index', $product->url_key) }}"
                            class="secondary-button"
                            target="_blank"
                        >
                            {{ trans('berkay::app.product-editor.view-site') }}
                        </a>
                    @endif

                    <!-- Save Button -->
                    <button type="submit" class="primary-button">
                        @lang('admin::app.catalog.products.edit.save-btn')
                    </button>
                </div>
            </div>
        </div>

        @php
            $channels = core()->getAllChannels();

            $currentChannel = core()->getRequestedChannel();

            $currentLocale = core()->getAllLocales()->firstWhere('code', $currentChannel->resolveLocaleCode(core()->getRequestedLocaleCode()));
        @endphp

        <input type="hidden" name="return_to_editor" value="1">
        @if ($errors->any())
            <div class="bk-workspace-errors" role="alert">
                <strong>{{ trans('berkay::collections.review-errors') }}</strong>
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <p class="bk-product-editor-help">{{ trans('berkay::app.product-editor.help') }}</p>
        <div class="bk-product-editor-status" role="status" aria-live="polite"></div>
        <nav class="bk-product-section-nav" aria-label="{{ trans('berkay::app.product-editor.sections') }}"></nav>
        @php
            $editableCodes = $product->getEditableAttributes()->pluck('code');
            $hasFeatured = $editableCodes->contains('featured');
            $hasSalePrice = $editableCodes->contains('special_price');
        @endphp
        @if ($hasFeatured || $hasSalePrice || $product->type === 'configurable')
            <section class="bk-workspace-sales bk-workspace-target" data-title="{{ trans('berkay::collections.sales-settings') }}">
                <h2>{{ trans('berkay::collections.sales-settings') }}</h2>
                @if ($hasFeatured)
                    <label for="featured">{{ trans('berkay::collections.featured-product') }}</label>
                    <select id="featured" name="featured">
                        <option value="0" @selected(! old('featured', $product->featured))>{{ trans('berkay::collections.no') }}</option>
                        <option value="1" @selected(old('featured', $product->featured))>{{ trans('berkay::collections.yes') }}</option>
                    </select>
                    <x-admin::form.control-group.error control-name="featured" />
                @endif
                @if ($hasSalePrice)
                    @include('berkay::admin.product-discount')
                    <x-admin::form.control-group.error control-name="discount_percent" />
                @elseif ($product->type === 'configurable')
                    <p>{{ trans('berkay::collections.variant-sale-help') }}</p>
                    <a href="#bk-workspace-variants">{{ trans('berkay::collections.edit-variants') }}</a>
                @endif
            </section>
        @endif
        <!-- Channel and Locale Switcher -->
        <div class="hidden">
            <div class="flex items-center gap-x-1">
                <!-- Channel Switcher -->
                <x-admin::dropdown :class="$channels->count() <= 1 ? 'hidden' : ''">
                    <!-- Dropdown Toggler -->
                    <x-slot:toggle>
                        <button
                            type="button"
                            class="transparent-button px-1 py-1.5 hover:bg-gray-200 focus:bg-gray-200 dark:text-white dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                        >
                            <span class="icon-store text-2xl"></span>

                            <span v-pre>{{ $currentChannel->name }}</span>

                            <input
                                type="hidden"
                                name="channel"
                                value="{{ $currentChannel->code }}"
                            />

                            <span class="icon-sort-down text-2xl"></span>
                        </button>
                    </x-slot>

                    <!-- Dropdown Content -->
                    <x-slot:content class="p-0!">
                        @foreach ($channels as $channel)
                            <a
                                href="?{{ Arr::query(['channel' => $channel->code, 'locale' => $channel->default_locale?->code ?? $currentLocale->code ]) }}"
                                class="flex cursor-pointer gap-2.5 px-5 py-2 text-base hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                                v-pre
                            >
                                {{ $channel->name }}
                            </a>
                        @endforeach
                    </x-slot>
                </x-admin::dropdown>

                <!-- Locale Switcher -->
                <x-admin::dropdown :class="$currentChannel->locales->count() <= 1 ? 'hidden' : ''">
                    <!-- Dropdown Toggler -->
                    <x-slot:toggle>
                        <button
                            type="button"
                            class="transparent-button px-1 py-1.5 hover:bg-gray-200 focus:bg-gray-200 dark:text-white dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                        >
                            <span class="icon-language text-2xl"></span>

                            <span v-pre>{{ $currentLocale->name }}</span>

                            <input
                                type="hidden"
                                name="locale"
                                value="{{ $currentLocale->code }}"
                            />

                            <span class="icon-sort-down text-2xl"></span>
                        </button>
                    </x-slot>

                    <!-- Dropdown Content -->
                    <x-slot:content class="p-0!">
                        @foreach ($currentChannel->locales->sortBy('name') as $locale)
                            <a
                                href="?{{ Arr::query(['channel' => $currentChannel->code, 'locale' => $locale->code]) }}"
                                class="flex gap-2.5 px-5 py-2 text-base cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-950 dark:text-white {{ $locale->code == $currentLocale->code ? 'bg-gray-100 dark:bg-gray-950' : ''}}"
                                v-pre
                            >
                                {{ $locale->name }}
                            </a>
                        @endforeach
                    </x-slot>
                </x-admin::dropdown>
            </div>
        </div>

        {!! view_render_event('bagisto.admin.catalog.product.edit.actions.after', ['product' => $product]) !!}

        <!-- Body Content -->
        {!! view_render_event('bagisto.admin.catalog.product.edit.form.before', ['product' => $product]) !!}

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            @php
                $groupedColumns = $product->attribute_family->attribute_groups->groupBy('column');

                $isSingleColumn = $groupedColumns->count() !== 2;
            @endphp

            @foreach ($groupedColumns as $column => $groups)
                {!! view_render_event("bagisto.admin.catalog.product.edit.form.column_{$column}.before", ['product' => $product]) !!}

                <div class="flex flex-col gap-2 {{ $column == 1 ? 'flex-1 max-xl:flex-auto' : 'w-90 max-w-full max-sm:w-full' }}">
                    @foreach ($groups as $group)
                        @php $customAttributes = $product->getEditableAttributes($group); @endphp

                        @if (
                            $group->code === 'inventories'
                            && (
                                $product->getTypeInstance()->isComposite()
                                || $product->type === 'downloadable'
                            )
                        )
                            @continue
                        @endif

                        @if ($group->code === 'rma')
                            @if (
                                ! in_array($product->type, explode(',', core()->getConfigData('sales.rma.setting.select_allowed_product_type')))
                                && (
                                    $product->type != 'simple'
                                    && empty($product->parent_id)
                                )
                            )
                                @continue
                            @endif
                        @endif

                        @if ($customAttributes->isNotEmpty())
                            {!! view_render_event("bagisto.admin.catalog.product.edit.form.{$group->code}.before", ['product' => $product]) !!}

                            <details class="bk-product-edit-section box-shadow relative rounded-sm bg-white p-4 dark:bg-gray-900" @if(!in_array($group->code, ['meta_description', 'shipping', 'settings', 'rma'])) open @endif>
                                <summary>{{ $group->name }}<span>{{ trans('berkay::app.product-editor.toggle') }}</span></summary>
                                <p
                                    class="hidden"
                                    v-pre
                                >
                                    {{ $group->name }}
                                </p>

                                @if ($group->code == 'meta_description')
                                    <x-admin::seo />
                                @endif

                                @foreach ($customAttributes as $attribute)
                                    @continue(($hasFeatured && $attribute->code === 'featured') || ($hasSalePrice && $attribute->code === 'special_price'))
                                    @if (in_array($attribute->code, ['sku', 'url_key', 'product_number', 'guest_checkout', 'visible_individually', 'manage_stock', 'tax_category_id', 'color', 'size', 'brand']))
                                        @php
                                            $hiddenValue = $product->{$attribute->code};
                                            if (is_bool($hiddenValue)) {
                                                $hiddenValue = (int) $hiddenValue;
                                            }
                                            if ($hiddenValue === null) {
                                                $hiddenValue = $attribute->code === 'url_key' ? 'product-'.strtolower($product->sku) : (in_array($attribute->code, ['guest_checkout', 'visible_individually', 'manage_stock']) ? 1 : '');
                                            }
                                        @endphp
                                        <input type="hidden" name="{{ $attribute->code }}" value="{{ $hiddenValue }}">
                                        @continue
                                    @endif

                                    {!! view_render_event("bagisto.admin.catalog.product.edit.form.{$group->code}.controls.before", ['product' => $product]) !!}

                                    <x-admin::form.control-group class="last:mb-0!">
                                        <x-admin::form.control-group.label>
                                            {!! e($attribute->admin_name) . ($attribute->is_required ? '<span class="required"></span>' : '') !!}

                                            @if (
                                                $attribute->value_per_channel
                                                && $channels->count() > 1
                                            )
                                                <span
                                                    class="rounded-sm border border-gray-200 bg-gray-100 px-1 py-0.5 text-[10px] font-semibold leading-normal text-gray-600"
                                                    v-pre
                                                >
                                                    {{ $currentChannel->name }}
                                                </span>
                                            @endif

                                            @if ($attribute->value_per_locale)
                                                <span
                                                    class="rounded-sm border border-gray-200 bg-gray-100 px-1 py-0.5 text-[10px] font-semibold leading-normal text-gray-600"
                                                    v-pre
                                                >
                                                    {{ $currentLocale->name }}
                                                </span>
                                            @endif
                                        </x-admin::form.control-group.label>

                                        @include ('admin::catalog.products.edit.controls', [
                                            'attribute' => $attribute,
                                            'product'   => $product,
                                        ])

                                        <x-admin::form.control-group.error :control-name="$attribute->code . (in_array($attribute->type, ['multiselect', 'checkbox']) ? '[]' : '')" />
                                    </x-admin::form.control-group>

                                    {!! view_render_event("bagisto.admin.catalog.product.edit.form.{$group->code}.controls.after", ['product' => $product]) !!}
                                @endforeach

                                @includeWhen($group->code == 'price', 'admin::catalog.products.edit.price.group')

                                @includeWhen($group->code === 'inventories', 'admin::catalog.products.edit.inventories')
                            </details>

                            {!! view_render_event("bagisto.admin.catalog.product.edit.form.{$group->code}.after", ['product' => $product]) !!}
                        @endif
                    @endforeach

                    @if ($column == 1)
                        <!-- Images View Blade File -->
                        <section class="bk-workspace-target" data-title="{{ trans('berkay::app.seller.images') }}">@include('admin::catalog.products.edit.images')</section>

                        <!-- Videos View Blade File -->
                        <details class="bk-product-extra"><summary>{{ trans('berkay::app.product-editor.video') }}</summary>@include('admin::catalog.products.edit.videos')</details>

                        <!-- Product Type View Blade File -->
                        <section id="bk-workspace-variants" class="bk-workspace-target" data-title="{{ trans('berkay::collections.edit-variants') }}">@includeIf('admin::catalog.products.edit.types.' . $product->type)</section>

                        <!-- Related, Cross Sells, Up Sells View Blade File -->
                        <details class="bk-product-extra"><summary>{{ trans('berkay::app.product-editor.related') }}</summary>@include('admin::catalog.products.edit.links')</details>

                        <!-- Include Product Type Additional Blade Files If Any -->
                        @foreach ($product->getTypeInstance()->getAdditionalViews() as $view)
                            @includeIf($view)
                        @endforeach
                    @elseif (! $isSingleColumn)
                        <!-- Channels View Blade File -->
                        <div class="hidden">@include('admin::catalog.products.edit.channels')</div>

                        <!-- Categories View Blade File -->
                        @include('admin::catalog.products.edit.categories')
                    @endif
                </div>

                @if (
                    $isSingleColumn
                    && (
                        $column == 1
                        || $column == 2
                    )
                )
                    <div class="w-90 max-w-full max-sm:w-full">
                        @if ($column == 2)
                            <!-- Images View Blade File -->
                            <section class="bk-workspace-target" data-title="{{ trans('berkay::app.seller.images') }}">@include('admin::catalog.products.edit.images')</section>

                            <!-- Videos View Blade File -->
                            <details class="bk-product-extra"><summary>{{ trans('berkay::app.product-editor.video') }}</summary>@include('admin::catalog.products.edit.videos')</details>

                            <!-- Product Type View Blade File -->
                            <section id="bk-workspace-variants" class="bk-workspace-target" data-title="{{ trans('berkay::collections.edit-variants') }}">@includeIf('admin::catalog.products.edit.types.' . $product->type)</section>

                            <!-- Related, Cross Sells, Up Sells View Blade File -->
                            <details class="bk-product-extra"><summary>{{ trans('berkay::app.product-editor.related') }}</summary>@include('admin::catalog.products.edit.links')</details>

                            <!-- Include Product Type Additional Blade Files If Any -->
                            @foreach ($product->getTypeInstance()->getAdditionalViews() as $view)
                                @includeIf($view)
                            @endforeach
                        @endif

                        <!-- Channels View Blade File -->
                        <div class="hidden">@include('admin::catalog.products.edit.channels')</div>

                        <!-- Categories View Blade File -->
                        @include('admin::catalog.products.edit.categories')
                    </div>
                @endif

                {!! view_render_event("bagisto.admin.catalog.product.edit.form.column_{$column}.after", ['product' => $product]) !!}
            @endforeach
        </div>

        {!! view_render_event('bagisto.admin.catalog.product.edit.form.after', ['product' => $product]) !!}
    </x-admin::form>

    {!! view_render_event('bagisto.admin.catalog.product.edit.after', ['product' => $product]) !!}
    </div>
    @include('berkay::admin.product-photo-crop', ['gallery' => true])
    @pushOnce('scripts')
        <script src="{{ asset('berkay/product-workspace.js') }}?v={{ filemtime(public_path('berkay/product-workspace.js')) }}" defer></script>
    @endPushOnce
</x-admin::layouts>
