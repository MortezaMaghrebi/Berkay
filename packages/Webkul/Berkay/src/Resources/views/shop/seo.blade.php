@php
    $privatePage = request()->is('customer', 'customer/*', 'checkout/*', 'search', 'compare', 'store-campaign/rewards');
    $localSite = in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1']);
@endphp
@if ($privatePage || $localSite)
    <meta name="robots" content="noindex, {{ request()->is('search') ? 'follow' : 'nofollow' }}">
@endif
