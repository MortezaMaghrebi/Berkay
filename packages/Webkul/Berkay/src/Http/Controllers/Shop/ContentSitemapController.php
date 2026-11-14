<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Webkul\Berkay\Repositories\ArticleRepository;
use Webkul\Shop\Http\Controllers\Controller;

class ContentSitemapController extends Controller
{
    /** Serve only published editorial URLs, with real modification dates. */
    public function index(ArticleRepository $articles): Response
    {
        $entries = $articles->scopeQuery(fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now()))->all();

        return response()->view('berkay::shop.content-sitemap', ['articles' => $entries], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** List canonical catalog URLs for the public channel and its default locale. */
    public function catalog(): Response
    {
        $channel = core()->getCurrentChannel();
        $locale = $channel->default_locale?->code ?? app()->getLocale();
        $root = $channel->root_category;

        $products = DB::table('product_flat')
            ->where('channel', $channel->code)
            ->where('locale', $locale)
            ->where('status', true)
            ->where('visible_individually', true)
            ->where('sku', 'not like', 'BK-SAMPLE-%')
            ->whereNotNull('url_key')
            ->where('url_key', '!=', '')
            ->selectRaw('url_key, MAX(updated_at) as updated_at')
            ->groupBy('url_key')
            ->orderBy('url_key')
            ->limit(49000)
            ->get();

        $categories = $root ? DB::table('categories')
            ->join('category_translations', 'category_translations.category_id', '=', 'categories.id')
            ->where('categories.status', true)
            ->where('categories._lft', '>', $root->_lft)
            ->where('categories._rgt', '<', $root->_rgt)
            ->where('category_translations.locale', $locale)
            ->whereNotNull('category_translations.slug')
            ->where('category_translations.slug', '!=', '')
            ->selectRaw('category_translations.slug, MAX(categories.updated_at) as updated_at')
            ->groupBy('category_translations.slug')
            ->orderBy('category_translations.slug')
            ->limit(990)
            ->get() : collect();

        return response()->view('berkay::shop.catalog-sitemap', compact('products', 'categories'), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
