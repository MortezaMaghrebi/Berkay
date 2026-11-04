<?php

namespace Webkul\Berkay\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Webkul\Berkay\Contracts\Article;
use Webkul\Core\Eloquent\Repository;

class ArticleRepository extends Repository
{
    /** Resolve the replaceable article contract. */
    public function model(): string
    {
        return Article::class;
    }

    /** Paginate editorial records for the management screen. */
    public function listing(): LengthAwarePaginator
    {
        return $this->model->newQuery()->latest('id')->paginate(20);
    }

    /** Paginate only articles whose publication time has arrived. */
    public function published(): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(12);
    }

    /** Find a public article without exposing drafts or scheduled content. */
    public function publishedBySlug(string $slug): mixed
    {
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();
    }
}
