<?php

namespace Webkul\Berkay\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Models\ProductProxy;

class ProductCollections
{
    public const KEYS = ['latest', 'featured', 'discounted', 'popular'];

    /** Query the public catalog using the same filters for the home rows and their full listings. */
    public function query(string $collection): Builder
    {
        $channel = core()->getCurrentChannel();

        $query = ProductProxy::modelClass()::query()
            ->select('products.*')
            ->whereNull('products.parent_id')
            ->whereHas('channels', fn ($channels) => $channels->where('channels.id', $channel->id))
            ->whereHas('product_flats', function ($flat) use ($channel, $collection) {
                $flat->where('channel', $channel->code)
                    ->where('locale', core()->getCurrentLocale()->code)
                    ->where('status', 1)
                    ->where('visible_individually', 1)
                    ->whereNotNull('url_key')
                    ->where('url_key', '<>', '')
                    ->when($collection === 'featured', fn ($flat) => $flat->where('featured', 1));
            })
            ->with(['images', 'attribute_values', 'attribute_family', 'price_indices']);

        if ($collection === 'discounted') {
            $groupId = app(CustomerRepository::class)->getCurrentGroup()->id;

            $discountedPrice = fn ($prices) => $prices
                ->where('channel_id', $channel->id)
                ->where('customer_group_id', $groupId)
                ->whereColumn('min_price', '<', 'regular_min_price');

            $query->where(fn ($products) => $products
                ->whereHas('price_indices', $discountedPrice)
                ->orWhereHas('variants', fn ($variants) => $variants
                    ->whereHas('price_indices', $discountedPrice)
                    ->whereHas('attribute_values', fn ($values) => $values
                        ->where('boolean_value', 1)
                        ->where(fn ($scope) => $scope->whereNull('channel')->orWhere('channel', $channel->code))
                        ->whereHas('attribute', fn ($attribute) => $attribute->where('code', 'status')))));
        }

        if ($collection === 'popular') {
            $query->join('berkay_product_views as views', function ($join) use ($channel) {
                $join->on('views.product_id', '=', 'products.id')
                    ->where('views.channel_id', $channel->id);
            })->where('views.total', '>', 0)->orderByDesc('views.total');
        }

        return $query->latest('products.created_at')->orderByDesc('products.id');
    }

    /** Count a storefront visit without losing increments during simultaneous requests. */
    public function recordView(int $productId): void
    {
        if (! $this->query('latest')->whereKey($productId)->exists()) {
            return;
        }

        $key = ['product_id' => $productId, 'channel_id' => core()->getCurrentChannel()->id];
        $views = DB::table('berkay_product_views');

        if (! $views->where($key)->increment('total')) {
            $views->insertOrIgnore($key + ['total' => 0]);
            $views->where($key)->increment('total');
        }
    }
}
