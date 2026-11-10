<meta name="theme-color" content="#ffffff">
@include('berkay::shop.seo')
<link rel="stylesheet" href="{{ asset('berkay/storefront.css') }}?v={{ filemtime(public_path('berkay/storefront.css')) }}">
<script src="{{ asset('berkay/storefront-interactions.js') }}?v={{ filemtime(public_path('berkay/storefront-interactions.js')) }}" defer></script>

<script src="{{ asset('berkay/storefront-editor.js') }}?v={{ filemtime(public_path('berkay/storefront-editor.js')) }}" defer></script>

<link rel="stylesheet" href="{{ asset('berkay/order-workflow.css') }}?v={{ filemtime(public_path('berkay/order-workflow.css')) }}">
