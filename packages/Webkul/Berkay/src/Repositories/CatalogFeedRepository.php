<?php

namespace Webkul\Berkay\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Contracts\Product;

class CatalogFeedRepository extends Repository
{
    /** Resolve the standard replaceable product contract. */
    public function model(): string
    {
        return Product::class;
    }

    /** Keep the existing feed policy until the store explicitly opts in. */
    public function exportsByDefault(int $channelId): bool
    {
        return (bool) (app(IntegrationSettings::class)->get('torob.export_default.'.$channelId) ?? true);
    }

    /** Set the policy for products without an explicit selection. */
    public function setExportDefault(int $channelId, bool $enabled): void
    {
        app(IntegrationSettings::class)->put('torob.export_default.'.$channelId, $enabled);
    }

    /** List the current channel's eligible products and their saved selections. */
    public function selectionPage(int $channelId, string $channelCode, string $locale, bool $default, array $filters): LengthAwarePaginator
    {
        $query = $this->forChannel($channelId, $channelCode, $locale)
            ->with('images')
            ->addSelect('product_flat.name as catalog_name', 'product_channels.torob_export')
            ->latest('products.id');

        $this->filterProducts($query, $filters);
        $this->filterSelection($query, $default, $filters['state'] ?? 'all');

        return $query->paginate(30)->appends($filters);
    }

    /** Count the eligible products currently selected for the feed. */
    public function exportCount(int $channelId, string $channelCode, string $locale, bool $default): int
    {
        $query = $this->forChannel($channelId, $channelCode, $locale);
        $this->filterSelection($query, $default, 'included');

        return $query->count();
    }

    /** Apply explicit choices only to eligible products on the current channel. */
    public function updateSelection(int $channelId, string $channelCode, string $locale, bool $default, array $filters, ?array $ids, bool $enabled): void
    {
        $eligible = $this->eligibleProducts($channelCode, $locale)->select('products.id');
        $query = $this->model->getConnection()->table('product_channels')->where('channel_id', $channelId);

        if ($ids === null) {
            $this->filterProducts($eligible, $filters);
            $this->filterSelection($query, $default, $filters['state'] ?? 'all');
        } else {
            $query->whereIn('product_id', $ids);
        }

        $query->whereIn('product_id', $eligible->toBase())->update(['torob_export' => $enabled]);
    }

    /** Return Torob's requested slice without exposing unpublished products. */
    public function feed(array $input, int $channelId, string $channelCode, string $locale): array
    {
        $query = $this->forChannel($channelId, $channelCode, $locale)
            ->with(['images', 'categories', 'price_indices', 'inventory_indices', 'variants']);
        $this->filterSelection($query, $this->exportsByDefault($channelId), 'included');

        if (isset($input['page_uniques'])) {
            $query->whereIn('products.id', $input['page_uniques']);
        }

        if (isset($input['page_urls'])) {
            $slugs = array_map(fn ($url) => trim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/'), $input['page_urls']);
            $query->whereIn('product_flat.url_key', $slugs);
        }

        $sort = $input['sort'] ?? 'product_id_desc';
        $column = match ($sort) {
            'date_added_desc' => 'created_at',
            'date_updated_desc' => 'updated_at',
            default => 'id',
        };

        $query->orderByDesc('products.'.$column);

        if ($column !== 'id') {
            $query->orderByDesc('products.id');
        }

        if (
            $sort === 'product_id_desc'
            && ! isset($input['page_uniques'])
            && ! isset($input['page_urls'])
        ) {
            if (isset($input['cursor'])) {
                $query->where('products.id', '<', $input['cursor']);
            }

            $items = $query->limit(101)->get();
            $hasMore = $items->count() > 100;
            $items = $items->take(100);

            return ['items' => $items, 'total' => null, 'max_pages' => null, 'current_page' => null, 'next_cursor' => $hasMore ? (string) $items->last()->id : null];
        }

        $page = $query->paginate(100, ['*'], 'page', $input['page'] ?? 1);

        return ['items' => $page->getCollection(), 'total' => $page->total(), 'max_pages' => $page->lastPage(), 'current_page' => $page->currentPage(), 'next_cursor' => null];
    }

    /** Join the one catalog row for the requested channel and locale. */
    private function eligibleProducts(string $channelCode, string $locale): Builder
    {
        return $this->model->newQuery()
            ->join('product_flat', 'product_flat.product_id', '=', 'products.id')
            ->whereNull('products.parent_id')
            ->where('product_flat.channel', $channelCode)
            ->where('product_flat.locale', $locale)
            ->where('product_flat.status', true)
            ->where('product_flat.visible_individually', true);
    }

    /** Restrict the catalog to products assigned to the channel. */
    private function forChannel(int $channelId, string $channelCode, string $locale): Builder
    {
        return $this->eligibleProducts($channelCode, $locale)
            ->join('product_channels', 'product_channels.product_id', '=', 'products.id')
            ->where('product_channels.channel_id', $channelId)
            ->select('products.*');
    }

    /** Filter by catalog name, SKU, or category. */
    private function filterProducts(Builder $query, array $filters): void
    {
        if (filled($filters['q'] ?? null)) {
            $search = '%'.$filters['q'].'%';
            $operator = db_grammar()->caseInsensitiveLike();
            $query->where(fn ($product) => $product->where('product_flat.name', $operator, $search)
                ->orWhere('products.sku', $operator, $search));
        }

        if (filled($filters['category'] ?? null)) {
            $query->whereHas('categories', fn ($category) => $category->where('categories.id', $filters['category']));
        }
    }

    /** Resolve explicit choices against the default feed policy. */
    private function filterSelection(Builder|QueryBuilder $query, bool $default, string $state): void
    {
        if ($state === 'all') {
            return;
        }

        $included = $state === 'included';
        $query->where(function ($query) use ($default, $included) {
            $query->where('product_channels.torob_export', $included);

            if ($default === $included) {
                $query->orWhereNull('product_channels.torob_export');
            }
        });
    }
}
