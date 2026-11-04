<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\View\View;
use Webkul\Berkay\Repositories\ArticleRepository;
use Webkul\Shop\Http\Controllers\Controller;

class MagazineController extends Controller
{
    /** Inject the magazine repository. */
    public function __construct(protected ArticleRepository $articles) {}

    /** Render published magazine articles. */
    public function index(): View
    {
        return view('berkay::shop.magazine.index', ['articles' => $this->articles->published()]);
    }

    /** Render an article that is publicly available. */
    public function show(string $slug): View
    {
        return view('berkay::shop.magazine.show', ['article' => $this->articles->publishedBySlug($slug)]);
    }
}
