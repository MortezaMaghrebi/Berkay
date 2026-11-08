<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Webkul\Berkay\Services\ProductCollections;
use Webkul\Product\Models\Product;

class ProductCollectionController
{
    public function __construct(protected ProductCollections $collections) {}

    /** Render fresh rows separately from the cached storefront home page. */
    public function rows(): View
    {
        $recommendations = $this->collections->query('latest')
            ->reorder()
            ->inRandomOrder()
            ->limit(40)
            ->get();

        $rows = [];

        foreach (ProductCollections::KEYS as $key) {
            if (! core()->getConfigData("berkay.home.collections.{$key}_enabled")) {
                continue;
            }

            $limit = (int) core()->getConfigData("berkay.home.collections.{$key}_limit");
            $limit = max(4, min(20, $limit ?: 10));
            $products = $this->collections->query($key)->limit($limit)->get();
            $isFallback = $products->isEmpty() && $key !== 'discounted';
            $suggestedIds = [];

            if ($key !== 'discounted' && $products->count() < $limit) {
                $suggestions = $recommendations->whereNotIn('id', $products->modelKeys())
                    ->shuffle()
                    ->take($limit - $products->count());

                $suggestedIds = $suggestions->modelKeys();
                $products = $products->concat($suggestions);
            }

            if ($products->isNotEmpty()) {
                $rows[$key] = compact('products', 'isFallback', 'suggestedIds');
            }
        }

        return view('berkay::shop.collections.rows', compact('rows', 'recommendations'));
    }

    /** Show the full ordered collection. */
    public function index(string $collection): View
    {
        return view('berkay::shop.collections.index', [
            'collection' => $collection,
            'products' => $this->collections->query($collection)->paginate(24),
        ]);
    }

    /** Count at most one view per browser session, product and half hour. */
    public function record(Request $request, Product $product): Response
    {
        $productId = (int) $product->getKey();
        $key = 'berkay-view:'.core()->getCurrentChannel()->id.':'.$productId;
        $sessionCache = $request->session()->cache();

        if (! $sessionCache->has($key)) {
            $this->collections->recordView($productId);
            $sessionCache->put($key, true, now()->addMinutes(30));
        }

        return response()->noContent();
    }
}
