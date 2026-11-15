<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Berkay\Repositories\CatalogFeedRepository;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Category\Repositories\CategoryRepository;

class TorobProductsController extends Controller
{
    /** Inject the catalog shared with the Torob API. */
    public function __construct(protected CatalogFeedRepository $catalog) {}

    /** Show the channel's product selection controls. */
    public function index(Request $request, CategoryRepository $categories, IntegrationSettings $settings): View|RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.integrations'), 403);

        $filters = $this->filters($request);
        $channel = core()->getCurrentChannel();
        $locale = $channel->default_locale->code;
        $default = $this->catalog->exportsByDefault($channel->id);
        $products = $this->catalog->selectionPage($channel->id, $channel->code, $locale, $default, $filters);

        if ($products->currentPage() > $products->lastPage()) {
            $request->session()->keep('success');

            return redirect()->route('admin.berkay.torob_products.index', $filters + ['page' => $products->lastPage()]);
        }

        return view('berkay::admin.torob-products', [
            'products' => $products,
            'categories' => array_intersect_key($categories->getCategoryPaths($locale), array_flip($categories->getVisibleCategoryIds($channel->root_category_id))),
            'exportCount' => $this->catalog->exportCount($channel->id, $channel->code, $locale, $default),
            'torobEnabled' => (bool) $settings->get('torob.enabled'),
            'defaultExport' => $default,
            'filters' => $filters,
            'canEdit' => bouncer()->hasPermission('berkay.integrations.edit'),
        ]);
    }

    /** Save one product, the checked products, or all matching products. */
    public function update(Request $request): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.integrations.edit'), 403);

        $filters = $this->filters($request);
        $data = $request->validate([
            'scope' => ['sometimes', 'required', Rule::in(['checked', 'filtered'])],
            'product_ids' => ['required_unless:scope,filtered', 'array', 'min:1', 'max:30'],
            'product_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'enabled' => ['required', 'boolean'],
        ]);
        $channel = core()->getCurrentChannel();
        $allMatches = ($data['scope'] ?? 'checked') === 'filtered';
        $this->catalog->updateSelection(
            $channel->id,
            $channel->code,
            $channel->default_locale->code,
            $this->catalog->exportsByDefault($channel->id),
            $filters,
            $allMatches ? null : $data['product_ids'],
            $request->boolean('enabled'),
        );

        return $this->saved($request, $filters);
    }

    /** Change the default selection without discarding explicit choices. */
    public function mode(Request $request): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.integrations.edit'), 403);

        $filters = $this->filters($request);
        $request->validate(['default_export' => ['required', 'boolean']]);
        $this->catalog->setExportDefault(core()->getCurrentChannel()->id, $request->boolean('default_export'));

        return $this->saved($request, $filters);
    }

    /** Validate the shared list and bulk-action filters. */
    private function filters(Request $request): array
    {
        return array_filter($request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
            'state' => ['nullable', Rule::in(['all', 'included', 'excluded'])],
        ]), fn ($value) => filled($value));
    }

    /** Return staff to the page they were editing. */
    private function saved(Request $request, array $filters): RedirectResponse
    {
        if ($request->integer('page') > 1) {
            $filters['page'] = $request->integer('page');
        }

        return redirect()->route('admin.berkay.torob_products.index', $filters)->with('success', trans('berkay::app.saved'));
    }
}
