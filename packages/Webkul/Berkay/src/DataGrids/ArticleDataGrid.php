<?php

namespace Webkul\Berkay\DataGrids;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ArticleDataGrid extends DataGrid
{
    /** Return the unpaginated query for native grid filtering. */
    public function prepareQueryBuilder()
    {
        return DB::table('berkay_articles')->select('id', 'title', 'category', 'status', 'published_at');
    }

    /** Define searchable editorial metadata and publication filters. */
    public function prepareColumns()
    {
        foreach (['title', 'category'] as $field) {
            $this->addColumn([
                'index' => $field, 'label' => trans('berkay::app.'.$field), 'type' => 'string',
                'searchable' => true, 'filterable' => true, 'sortable' => true,
            ]);
        }

        $this->addColumn([
            'index' => 'status', 'label' => trans('berkay::app.status'), 'type' => 'string',
            'filterable' => true, 'sortable' => true, 'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('berkay::app.draft'), 'value' => 'draft'],
                ['label' => trans('berkay::app.published'), 'value' => 'published'],
            ],
            'closure' => fn ($row) => e(trans('berkay::app.'.$row->status)),
        ]);

        $this->addColumn([
            'index' => 'published_at', 'label' => trans('berkay::app.published-at'), 'type' => 'datetime',
            'closure' => fn ($row) => $row->published_at ? e(core()->formatDate($row->published_at)) : '—',
            'filterable' => true, 'sortable' => true,
        ]);
    }

    /** Show editing only to staff with the matching permission. */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('berkay.articles.edit')) {
            $this->addAction([
                'icon' => 'icon-edit', 'title' => trans('berkay::app.edit'), 'method' => 'GET',
                'url' => fn ($row) => route('admin.berkay.articles.edit', $row->id),
            ]);
        }
    }
}
