<?php

namespace Webkul\Berkay\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Berkay\Repositories\SellerCatalogRepository;

class ProductEditorAccess
{
    /** Authorize staff before native product validation or form rendering runs. */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth()->guard('admin')->check(), 401);
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);
        app(SellerCatalogRepository::class)->storeProduct((int) $request->route('id'));

        return $next($request);
    }
}
