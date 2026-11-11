<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Berkay\Http\Requests\SellerCategoryRequest;
use Webkul\Berkay\Http\Requests\SellerProductRequest;
use Webkul\Berkay\Repositories\SellerCatalogRepository;
use Webkul\Berkay\Repositories\SellerCategoryRepository;

class SimpleCatalogController extends Controller
{
    /** Inject scoped catalog boundaries. */
    public function __construct(protected SellerCatalogRepository $products, protected SellerCategoryRepository $categories) {}

    /** Show products with short, familiar actions. */
    public function index(Request $request): View
    {
        abort_unless(bouncer()->hasPermission('catalog.products'), 403);
        $search = $request->validate(['query' => ['nullable', 'string', 'max:200']])['query'] ?? '';

        return view('berkay::admin.seller-products', ['products' => $this->products->listing($search)]);
    }

    /** Show the short everyday product form. */
    public function create(): View
    {
        abort_unless(bouncer()->hasPermission('catalog.products.create'), 403);

        return view('berkay::admin.simple-product', ['categories' => $this->categories->storeTree(), 'product' => null]);
    }

    /** Edit simple products while keeping complex types in the advanced editor. */
    public function edit(int $id): View|RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);
        $product = $this->products->storeProduct($id);
        if ($product->type !== 'simple') {
            return redirect()->route('admin.catalog.products.edit', $id);
        }

        return view('berkay::admin.simple-product', ['categories' => $this->categories->storeTree(), 'product' => $product]);
    }

    /** Create a product with automatic catalog defaults. */
    public function store(SellerProductRequest $request): RedirectResponse
    {
        $product = $this->products->saveSimple($request->validated());

        return redirect()->route('admin.berkay.simple_product.edit', $product->id)->with('success', trans('berkay::app.seller.product-saved'));
    }

    /** Update price, stock and photos without losing advanced product settings. */
    public function update(SellerProductRequest $request, int $id): RedirectResponse
    {
        $this->products->saveSimple($request->validated(), $id);

        return redirect()->route('admin.berkay.simple_product.edit', $id)->with('success', trans('berkay::app.seller.product-saved'));
    }

    /** List categories without technical columns. */
    public function categories(): View
    {
        abort_unless(bouncer()->hasPermission('catalog.categories'), 403);

        return view('berkay::admin.seller-categories', ['categories' => $this->categories->storeTree()]);
    }

    /** Show only understandable category fields. */
    public function category(): View
    {
        abort_unless(bouncer()->hasPermission('catalog.categories.create'), 403);

        return view('berkay::admin.simple-category', ['categories' => $this->categories->storeTree(), 'category' => null]);
    }

    /** Edit category names, parent groups and visibility. */
    public function editCategory(int $id): View
    {
        abort_unless(bouncer()->hasPermission('catalog.categories.edit'), 403);

        return view('berkay::admin.simple-category', ['categories' => $this->categories->storeTree(), 'category' => $this->categories->storeCategory($id)]);
    }

    /** Choose ordering and SEO automatically for new categories. */
    public function storeCategory(SellerCategoryRequest $request): RedirectResponse
    {
        $this->categories->saveSimple($request->validated());

        return redirect()->route('admin.berkay.simple_category.index')->with('success', trans('berkay::app.seller.category-saved'));
    }

    /** Delete a category from the active store. */
    public function deleteCategory(int $id): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('catalog.categories.delete'), 403);
        $this->categories->deleteSimple($id);

        return redirect()->route('admin.berkay.simple_category.index')->with('success', trans('berkay::category-delete.deleted'));
    }

    /** Save category edits through the scoped repository. */
    public function updateCategory(SellerCategoryRequest $request, int $id): RedirectResponse
    {
        $this->categories->saveSimple($request->validated(), $id);

        return redirect()->route('admin.berkay.simple_category.index')->with('success', trans('berkay::app.seller.category-saved'));
    }
}
