<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\Response;
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
}
