<?php

namespace Webkul\Berkay\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Webkul\Berkay\Support\ProductDiscount;
use Webkul\Product\Jobs\UpdateCreateInventoryIndex;
use Webkul\Product\Jobs\UpdateCreatePriceIndex;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Product\Services\Search\SearchEngineManager;

class SellerCatalogRepository extends ProductRepository
{
    /** Update selected text attributes without syncing media, stock or relations. */
    public function saveInlineText(int $id, array $data): mixed
    {
        $product = $this->storeProduct($id);
        $saved = DB::transaction(function () use ($product, $data) {
            Event::dispatch('catalog.product.update.before', $product);
            $saved = parent::update($data + ['channel' => core()->getCurrentChannel()->code, 'locale' => core()->getCurrentLocale()->code], $product->id, ['name', 'short_description']);
            Event::dispatch('catalog.product.update.after', $saved);

            return $saved;
        });
        app(SearchEngineManager::class)->indexer()->indexBatch([$saved->fresh()]);

        return $saved;
    }

    /** Find store-owned parent products with an optional name or SKU search. */
    public function listing(string $search = ''): LengthAwarePaginator
    {
        $channel = core()->getCurrentChannel();

        return $this->model->newQuery()->whereNull('parent_id')
            ->whereHas('channels', fn ($query) => $query->where('channels.id', $channel->id))
            ->when($search !== '', function ($query) use ($search, $channel) {
                $query->where(function ($scope) use ($search, $channel) {
                    $scope->where('sku', db_grammar()->caseInsensitiveLike(), '%'.$search.'%')
                        ->orWhereHas('product_flats', fn ($flat) => $flat->where('channel', $channel->code)
                            ->where('locale', core()->getCurrentLocale()->code)
                            ->where('name', db_grammar()->caseInsensitiveLike(), '%'.$search.'%'));
                });
            })->latest('id')->paginate(15)->withQueryString();
    }

    /** Require the selected product to belong to the active store. */
    public function storeProduct(int $id): mixed
    {
        return $this->model->newQuery()->whereNull('parent_id')
            ->whereHas('channels', fn ($query) => $query->where('channels.id', core()->getCurrentChannel()->id))
            ->findOrFail($id);
    }

    /** Save a simple product while preserving advanced media, prices and relations. */
    public function saveSimple(array $data, ?int $id = null): mixed
    {
        $saved = DB::transaction(function () use ($data, $id) {
            $channel = core()->getCurrentChannel();
            $source = $channel->inventory_sources->first();

            $product = $id ? $this->storeProduct($id) : null;
            abort_if($product && $product->type !== 'simple', 422);
            $manageStock = array_key_exists('unlimited_stock', $data)
                ? ! $data['unlimited_stock']
                : ($product ? (bool) $product->manage_stock : true);
            abort_if($manageStock && ! $source, 422, trans('berkay::app.seller.inventory-required'));
            $sku = $product?->sku ?? 'BK-'.strtoupper((string) Str::ulid());
            Event::dispatch('catalog.product.'.($id ? 'update' : 'create').'.before', $product);
            $product ??= parent::create(['type' => 'simple', 'attribute_family_id' => 1, 'sku' => $sku]);
            $description = '<p>'.nl2br(e(($data['description'] ?? '') ?: $data['name'])).'</p>';
            $images = $product->images->mapWithKeys(fn ($image) => [$image->id => $image->path])->all();
            foreach ($data['images'] ?? [] as $index => $image) {
                $images['new_'.$index] = $image;
            }
            $payload = [
                'channel' => $channel->code, 'locale' => core()->getCurrentLocale()->code,
                'sku' => $sku, 'name' => $data['name'], 'price' => $data['price'],
                'url_key' => $product->url_key ?: 'product-'.strtolower($sku),
                'description' => $description, 'short_description' => $description,
                'status' => $data['status'], 'visible_individually' => 1,
                'new' => $id ? $product->new : 1,
                'featured' => $data['featured'] ?? $product->featured ?? 0,
                'weight' => $product->weight ?: '0.5',
                'guest_checkout' => 1, 'manage_stock' => (int) $manageStock,
                'channels' => array_unique([...$product->channels->pluck('id')->all(), $channel->id]),
                'categories' => [$data['category_id']],
                'images' => ['files' => $images],
                'videos' => ['files' => $product->videos->mapWithKeys(fn ($video) => [$video->id => $video->path])->all()],
                'up_sells' => $product->up_sells->pluck('id')->all(),
                'cross_sells' => $product->cross_sells->pluck('id')->all(),
                'related_products' => $product->related_products->pluck('id')->all(),
                'customer_group_prices' => $product->customer_group_prices->mapWithKeys(fn ($price) => [$price->id => $price->only(['qty', 'value_type', 'value', 'customer_group_id'])])->all(),
                'meta_title' => $product->meta_title ?: $data['name'],
                'meta_description' => $product->meta_description ?: Str::limit(strip_tags($description), 155, ''),
            ];
            if ($manageStock && isset($data['quantity'])) {
                $payload['inventories'] = [$source->id => $data['quantity']];
            }
            if (isset($data['discount_percent'])) {
                $payload['special_price'] = ProductDiscount::price((float) $data['price'], (float) $data['discount_percent']) ?? '';
                $payload['special_price_from'] = '';
                $payload['special_price_to'] = '';
            } elseif (array_key_exists('special_price', $data)) {
                $payload['special_price'] = $data['special_price'] ?? '';
            }

            $saved = parent::update($payload, $product->id);
            Event::dispatch('catalog.product.'.($id ? 'update' : 'create').'.after', $saved);
            if (! $id) {
                Event::dispatch('catalog.product.update.after', $saved);
            }

            return $saved;
        });
        app(UpdateCreateInventoryIndex::class, ['productIds' => [$saved->id]])->handle();
        app(UpdateCreatePriceIndex::class, ['productIds' => [$saved->id]])->handle();
        app(SearchEngineManager::class)->indexer()->indexBatch([$saved->fresh()]);

        return $saved;
    }
}
