<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Catalog\ProductController;
use Webkul\Admin\Http\Requests\ProductForm;
use Webkul\Berkay\Repositories\SellerCatalogRepository;
use Webkul\Core\Http\Middleware\SecureHeaders;
use Webkul\Shop\Http\Controllers\Controller;

class StorefrontEditorController extends Controller
{
    /** Return private editor capabilities without placing staff data in public HTML. */
    public function session(Request $request, SellerCatalogRepository $products): JsonResponse
    {
        if (! auth()->guard('admin')->check()) {
            return response()->json(['admin' => false])->header('Cache-Control', 'private, no-store');
        }

        $data = ['admin' => true, 'admin_url' => url(config('app.admin_url'))];
        $id = $request->validate(['product_id' => ['nullable', 'integer', 'min:1']])['product_id'] ?? null;

        if ($id && bouncer()->hasPermission('catalog.products.edit')) {
            $product = $products->storeProduct($id);
            $data += ['editable' => true, 'csrf' => csrf_token(), 'edit_url' => route('shop.berkay.full_editor', $id), 'save_url' => route('shop.berkay.inline_product', $id), 'simple' => $product->type === 'simple'];
        }

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    /** Allow the authorized product form to be framed only by this same origin. */
    public function editor(Request $request, int $id, SellerCatalogRepository $products): View
    {
        abort_unless(auth()->guard('admin')->check(), 401);
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);
        $product = $products->storeProduct($id);
        $request->attributes->set(SecureHeaders::FRAMABLE, true);
        themes()->set(config('themes.admin-default'));

        return view('admin::catalog.products.edit', compact('product'));
    }

    /** Reuse native validation and saving, returning to the embedded product form. */
    public function saveEditor(ProductForm $request, int $id, SellerCatalogRepository $products): RedirectResponse
    {
        $products->storeProduct($id);
        app(ProductController::class)->update($request, $id);

        return redirect()->route('shop.berkay.full_editor', $id)->with('success', trans('berkay::app.saved'));
    }

    /** Save only explicit text attributes while preserving media and product relations. */
    public function update(Request $request, int $id, SellerCatalogRepository $products): JsonResponse
    {
        abort_unless(auth()->guard('admin')->check(), 401);
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);
        $products->storeProduct($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'short_description' => ['required', 'string', 'max:5000'],
        ]);
        $data['short_description'] = '<p>'.nl2br(e($data['short_description'])).'</p>';
        $products->saveInlineText($id, $data);

        return response()->json(['message' => trans('berkay::app.saved')])->header('Cache-Control', 'private, no-store');
    }
}
