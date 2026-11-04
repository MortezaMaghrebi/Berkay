<?php

namespace Webkul\Berkay\DataGrids;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class PaymentDataGrid extends DataGrid
{
    /** Return payment metadata without exposing callback tokens or authorities. */
    public function prepareQueryBuilder()
    {
        return DB::table('berkay_payment_attempts')->select('id', 'order_id', 'provider', 'amount_rial', 'status', 'receipt', 'created_at');
    }

    /** Define searchable receipts, amounts and reconciliation states. */
    public function prepareColumns()
    {
        foreach (['order_id' => 'integer', 'provider' => 'string', 'amount_rial' => 'integer', 'receipt' => 'string', 'created_at' => 'datetime'] as $field => $type) {
            $this->addColumn([
                'index' => $field, 'label' => trans('berkay::app.'.str_replace('_', '-', $field)), 'type' => $type,
                'searchable' => in_array($field, ['provider', 'receipt'], true), 'filterable' => true, 'sortable' => true,
            ]);
        }

        $this->addColumn([
            'index' => 'status', 'label' => trans('berkay::app.status'), 'type' => 'string', 'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => array_map(fn ($state) => ['label' => trans('berkay::app.'.$state), 'value' => $state], ['pending', 'verified', 'paid']),
            'closure' => fn ($row) => e(trans('berkay::app.'.$row->status)),
        ]);
    }

    /** Link payment rows to the core order management screen. */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('sales.orders.view')) {
            $this->addAction([
                'icon' => 'icon-view', 'title' => trans('berkay::app.orders'), 'method' => 'GET',
                'url' => fn ($row) => route('admin.sales.orders.view', $row->order_id),
            ]);
        }
    }
}
