<?php

namespace Webkul\Berkay\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Contracts\Product;

class CatalogFeedRepository extends Repository
{
    /** Resolve the standard replaceable product contract. */
    public function model(): string
    {
        return Product::class;
    }

    /** Build a channel-scoped public catalog query with deterministic ordering. */
    public function queryForChannel(int $channelId, string $channelCode, string $locale): mixed
    {
        return $this->model->newQuery()
            ->with(['images', 'categories', 'price_indices', 'inventory_indices', 'variants'])
            ->whereNull('parent_id')
            ->whereHas('channels', fn ($query) => $query->where('channels.id', $channelId))
            ->whereHas('product_flats', fn ($query) => $query
                ->where('channel', $channelCode)
                ->where('locale', $locale)
                ->where('status', 1)
                ->where('visible_individually', 1));
    }

    /** Return Torob's requested slice without exposing unpublished products. */
    public function feed(array $input, int $channelId, string $channelCode, string $locale): array
    {
        $query = $this->queryForChannel($channelId, $channelCode, $locale);

        if (isset($input['page_uniques'])) {
            $query->whereIn('id', $input['page_uniques']);
        }

        if (isset($input['page_urls'])) {
            $slugs = array_map(fn ($url) => trim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/'), $input['page_urls']);
            $query->whereHas('product_flats', fn ($flat) => $flat
                ->where('channel', $channelCode)
                ->where('locale', $locale)
                ->whereIn('url_key', $slugs));
        }

        $sort = $input['sort'] ?? 'product_id_desc';
        $column = match ($sort) {
            'date_added_desc' => 'created_at',
            'date_updated_desc' => 'updated_at',
            default => 'id',
        };

        $query->orderByDesc($column);

        if ($column !== 'id') {
            $query->orderByDesc('id');
        }

        if (
            $sort === 'product_id_desc'
            && ! isset($input['page_uniques'])
            && ! isset($input['page_urls'])
        ) {
            if (isset($input['cursor'])) {
                $query->where('id', '<', $input['cursor']);
            }

            $items = $query->limit(101)->get();
            $hasMore = $items->count() > 100;
            $items = $items->take(100);

            return ['items' => $items, 'total' => null, 'max_pages' => null, 'current_page' => null, 'next_cursor' => $hasMore ? (string) $items->last()->id : null];
        }

        $page = $query->paginate(100, ['*'], 'page', $input['page'] ?? 1);

        return ['items' => $page->getCollection(), 'total' => $page->total(), 'max_pages' => $page->lastPage(), 'current_page' => $page->currentPage(), 'next_cursor' => null];
    }
}
