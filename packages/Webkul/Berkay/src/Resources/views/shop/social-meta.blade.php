<meta property="og:type" content="{{ $shareType ?? 'website' }}">
<meta property="og:site_name" content="{{ core()->getConfigData('berkay.store.identity.name') ?: trans('berkay::app.brand') }}">
<meta property="og:title" content="{{ $shareTitle }}">
<meta property="og:description" content="{{ $shareDescription }}">
<meta property="og:url" content="{{ $shareUrl }}">
<meta property="og:image" content="{{ $shareImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $shareTitle }}">
<meta name="twitter:description" content="{{ $shareDescription }}">
<meta name="twitter:image" content="{{ $shareImage }}">
