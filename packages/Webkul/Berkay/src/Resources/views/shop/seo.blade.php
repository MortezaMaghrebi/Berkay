@php
    $privatePage = request()->is('customer/*', 'checkout/*', 'search', 'compare', 'admin/*');
    $localSite = in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1']);
    $samplePage = isset($product) && str_starts_with((string) $product->sku, 'BK-SAMPLE-');
@endphp
@if ($privatePage || $localSite || $samplePage)
    <meta name="robots" content="noindex, follow">
@endif
