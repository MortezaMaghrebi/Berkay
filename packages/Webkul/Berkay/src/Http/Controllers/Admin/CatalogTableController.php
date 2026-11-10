<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Berkay\Services\CatalogTable;

class CatalogTableController
{
    public function __construct(protected CatalogTable $table) {}

    public function index(Request $request): View
    {
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';
        $products = $this->table->parents()
            ->with(['images', 'attribute_values', 'inventories', 'super_attributes', 'variants.attribute_values', 'variants.inventories'])
            ->when($search !== '', fn ($query) => $query->whereHas('product_flats', fn ($flat) => $flat
                ->where('channel', core()->getCurrentChannel()->code)
                ->where('name', db_grammar()->caseInsensitiveLike(), '%'.$search.'%')))
            ->latest('id')->paginate(20)->withQueryString();

        return view('berkay::admin.catalog-table', [
            'products' => $products,
            'table' => $this->table,
            'color' => $this->table->colors(),
            'source' => $this->table->inventorySource(),
            'materials' => app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findOneByField('code', 'material')?->options ?? collect(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);

        $data = $request->validate([
            'rows' => ['required', 'array', 'max:250'],
            'rows.*' => ['required', 'array:key,id,parent,color,name,price,quantity,discount,status,color_description,material,feature1,feature2,feature3'],
            'rows.*.key' => ['required', 'string', 'distinct', 'max:64'],
            'rows.*.id' => ['nullable', 'integer', 'min:1'],
            'rows.*.parent' => ['required', 'integer', 'min:1'],
            'rows.*.color' => ['nullable', 'integer', 'min:1'],
            'rows.*.name' => ['sometimes', 'required', 'string', 'max:200'],
            'rows.*.price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:'.CatalogTable::MAX_PRICE],
            'rows.*.quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
            'rows.*.discount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99.99'],
            'rows.*.status' => ['sometimes', 'required', 'boolean'],
            'rows.*.color_description' => ['nullable', 'string', 'max:300'],
            'rows.*.material' => ['nullable', 'string', 'max:100'],
            'rows.*.feature1' => ['nullable', 'string', 'max:200'],
            'rows.*.feature2' => ['nullable', 'string', 'max:200'],
            'rows.*.feature3' => ['nullable', 'string', 'max:200'],
        ]);

        return response()->json(['rows' => $this->table->save($data['rows']), 'message' => trans('berkay::app.saved')]);
    }

    public function destroy(int $parent, int $item): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('catalog.products.delete'), 403);
        $this->table->remove($parent, $item);

        return response()->json(['message' => trans('berkay::table.deleted')]);
    }

    public function adjust(Request $request): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:-'.CatalogTable::MAX_PRICE, 'max:'.CatalogTable::MAX_PRICE, 'not_in:0'],
            'scope' => ['required', Rule::in(['all', 'products', 'variants'])],
        ]);
        if ($data['mode'] === 'percent') {
            $request->validate(['value' => ['numeric', 'between:-100,1000']]);
        }

        return response()->json([
            'message' => trans('berkay::table.adjusted', ['count' => $this->table->adjust($data['mode'], $data['value'], $data['scope'])]),
        ]);
    }
}
