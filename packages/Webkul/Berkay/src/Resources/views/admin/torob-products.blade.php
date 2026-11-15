<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::torob.title') }}</x-slot>

    @php
        $indexUrl = route('admin.berkay.torob_products.index');
        $returnQuery = $products->currentPage() > 1 ? $filters + ['page' => $products->currentPage()] : $filters;
        $updateUrl = route('admin.berkay.torob_products.update', $returnQuery);
        $state = $filters['state'] ?? 'all';
    @endphp

    <section class="bk-admin bk-torob" dir="{{ core()->getCurrentLocale()->direction }}">
        <header class="bk-toolbar bk-torob-heading">
            <div>
                <h1>{{ trans('berkay::torob.title') }}</h1>
                <p>{{ trans('berkay::torob.description') }}</p>
            </div>
            <a class="bk-outline-button" href="{{ route('admin.berkay.integrations.index') }}#torob">{{ trans('berkay::torob.connection') }}</a>
        </header>

        @include('berkay::admin.seller-feedback')

        <div class="bk-torob-policy">
            <div>
                <p class="bk-torob-count">{{ trans('berkay::torob.export-count', ['count' => number_format($exportCount)]) }}</p>
                <p>{{ trans($torobEnabled ? 'berkay::torob.connection-active' : 'berkay::torob.connection-paused') }}</p>
            </div>
            @if ($canEdit)
                <form class="bk-torob-mode" method="post" action="{{ route('admin.berkay.torob_products.mode', $returnQuery) }}">
                    @csrf
                    @method('PUT')
                    <label for="torob-mode">{{ trans('berkay::torob.default-policy') }}</label>
                    <select name="default_export" id="torob-mode">
                        <option value="1" @selected($defaultExport)>{{ trans('berkay::torob.automatic') }}</option>
                        <option value="0" @selected(! $defaultExport)>{{ trans('berkay::torob.manual') }}</option>
                    </select>
                    <button class="bk-outline-button" type="submit">{{ trans('berkay::torob.save-policy') }}</button>
                </form>
            @endif
        </div>

        <form class="bk-form bk-torob-filters" method="get" action="{{ $indexUrl }}">
            <div>
                <label for="torob-search">{{ trans('berkay::torob.search') }}</label>
                <input type="search" id="torob-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120">
            </div>
            <div>
                <label for="torob-category">{{ trans('berkay::app.seller.categories') }}</label>
                <select id="torob-category" name="category">
                    <option value="">{{ trans('berkay::torob.all-categories') }}</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected((int) ($filters['category'] ?? 0) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($state !== 'all')
                <input type="hidden" name="state" value="{{ $state }}">
            @endif
            <button class="bk-outline-button" type="submit">{{ trans('admin::app.configuration.index.search') }}</button>
        </form>

        <div class="bk-torob-results">
            <nav class="bk-torob-tabs" aria-label="{{ trans('berkay::torob.filter-status') }}">
                @foreach (['all', 'included', 'excluded'] as $tab)
                    <a href="{{ route('admin.berkay.torob_products.index', [...$filters, 'state' => $tab]) }}" @if ($state === $tab) aria-current="page" @endif>
                        {{ trans('berkay::torob.'.$tab) }}
                    </a>
                @endforeach
            </nav>
            <p>{{ trans('berkay::torob.results', ['count' => number_format($products->total())]) }}</p>
        </div>

        @if ($canEdit && $products->count())
            <form id="torob-bulk" class="bk-torob-bulk" method="post" action="{{ $updateUrl }}"
                data-picked-label="{{ trans('berkay::torob.picked-count') }}" data-result-count="{{ $products->total() }}">
                @csrf
                @method('PUT')
                <div class="bk-torob-selection">
                    <label hidden><input type="checkbox" data-torob-page> {{ trans('berkay::torob.pick-page') }}</label>
                    @if ($products->total() > $products->count())
                        <label><input type="checkbox" name="scope" value="filtered" data-torob-filtered> {{ trans('berkay::torob.pick-filtered', ['count' => number_format($products->total())]) }}</label>
                    @endif
                    <output data-torob-count aria-live="polite"></output>
                </div>
                <div class="bk-torob-actions">
                    <button class="bk-button" type="submit" name="enabled" value="1">{{ trans('berkay::torob.include') }}</button>
                    <button class="bk-outline-button" type="submit" name="enabled" value="0">{{ trans('berkay::torob.exclude') }}</button>
                </div>
            </form>
        @endif

        <ul class="bk-torob-list">
            @forelse ($products as $product)
                @php $included = (bool) ($product->torob_export ?? $defaultExport); @endphp
                <li class="bk-torob-row">
                    @if ($canEdit)
                        <label class="bk-torob-pick">
                            <input form="torob-bulk" name="product_ids[]" type="checkbox" value="{{ $product->id }}" data-torob-product>
                            <span class="sr-only">{{ $product->catalog_name ?: $product->sku }}</span>
                        </label>
                    @endif
                    @if ($photo = $product->images->first())
                        <img src="{{ asset('storage/'.$photo->path) }}" width="48" height="48" alt="" loading="lazy">
                    @else
                        <span class="bk-torob-image-placeholder icon-image" aria-hidden="true"></span>
                    @endif
                    <div class="bk-torob-product">
                        <h2>{{ $product->catalog_name ?: $product->sku }}</h2>
                        <bdi>{{ $product->sku }}</bdi>
                    </div>
                    <span class="bk-torob-state" @if ($included) data-included @endif>
                        {{ trans($included ? 'berkay::torob.included' : 'berkay::torob.excluded') }}
                    </span>
                    @if ($canEdit)
                        <form method="post" action="{{ $updateUrl }}" class="bk-torob-row-action">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="product_ids[]" value="{{ $product->id }}">
                            <button class="bk-text-link" type="submit" name="enabled" value="{{ $included ? '0' : '1' }}">
                                {{ trans($included ? 'berkay::torob.exclude' : 'berkay::torob.include') }}
                            </button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="bk-torob-empty">{{ trans('berkay::torob.empty') }} <a class="bk-text-link" href="{{ $indexUrl }}">{{ trans('berkay::torob.clear-filters') }}</a></li>
            @endforelse
        </ul>

        @if ($products->hasPages())
            <nav class="bk-torob-pagination" aria-label="{{ trans('berkay::torob.results', ['count' => number_format($products->total())]) }}">
                <p>{{ trans('shop::app.partials.pagination.pagination-showing', [
                    'firstItem' => $products->firstItem(),
                    'lastItem' => $products->lastItem(),
                    'total' => $products->total(),
                ]) }}</p>
                <div class="bk-torob-actions">
                    @if ($products->previousPageUrl())
                        <a class="bk-outline-button" href="{{ $products->previousPageUrl() }}">{{ trans('shop::app.partials.pagination.prev-page') }}</a>
                    @endif
                    @if ($products->nextPageUrl())
                        <a class="bk-outline-button" href="{{ $products->nextPageUrl() }}">{{ trans('shop::app.partials.pagination.next-page') }}</a>
                    @endif
                </div>
            </nav>
        @endif
    </section>

    @pushOnce('scripts')
        <script type="module" src="{{ asset('berkay/torob-products.js') }}?v={{ filemtime(public_path('berkay/torob-products.js')) }}"></script>
    @endPushOnce
</x-admin::layouts>
