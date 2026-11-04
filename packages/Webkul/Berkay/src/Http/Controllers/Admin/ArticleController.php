<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Berkay\DataGrids\ArticleDataGrid;
use Webkul\Berkay\Http\Requests\ArticleRequest;
use Webkul\Berkay\Repositories\ArticleRepository;

class ArticleController extends Controller
{
    /** Inject the editorial repository. */
    public function __construct(protected ArticleRepository $articles) {}

    /** List editorial records for authorized staff. */
    public function index(): View|JsonResponse|BinaryFileResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.articles'), 403);

        if (request()->ajax()) {
            return datagrid(ArticleDataGrid::class)->process();
        }

        return view('berkay::admin.articles.index');
    }

    /** Display a new article form. */
    public function create(): View
    {
        abort_unless(bouncer()->hasPermission('berkay.articles.create'), 403);

        return view('berkay::admin.articles.edit', ['article' => null]);
    }

    /** Store validated editorial content. */
    public function store(ArticleRequest $request): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.articles.create'), 403);
        $this->articles->create($this->sanitized($request));

        return redirect()->route('admin.berkay.articles.index')->with('success', trans('berkay::app.saved'));
    }

    /** Display an existing article for editing. */
    public function edit(int $id): View
    {
        abort_unless(bouncer()->hasPermission('berkay.articles.edit'), 403);

        return view('berkay::admin.articles.edit', ['article' => $this->articles->findOrFail($id)]);
    }

    /** Update the requested editorial record. */
    public function update(ArticleRequest $request, int $id): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.articles.edit'), 403);
        $this->articles->findOrFail($id);
        $this->articles->update($this->sanitized($request), $id);

        return redirect()->route('admin.berkay.articles.index')->with('success', trans('berkay::app.saved'));
    }

    /** Remove an article after an authorized explicit action. */
    public function destroy(int $id): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.articles.delete'), 403);
        $this->articles->delete($id);

        return redirect()->route('admin.berkay.articles.index')->with('success', trans('berkay::app.saved'));
    }

    /** Validate fields and sanitize rich content before persistence. */
    protected function sanitized(ArticleRequest $request): array
    {
        $data = $request->validated();
        $data['body'] = clean_content($data['body']);

        if ($data['status'] === 'published') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        return $data;
    }
}
