<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Berkay\Services\OrderWorkflow;
use Webkul\Sales\Models\Order;

class WorkshopController
{
    public function __construct(protected OrderWorkflow $workflow) {}

    public function index(): View
    {
        abort_unless(bouncer()->hasPermission('sales.orders'), 403);
        $orders = Order::query()->where('channel_id', core()->getCurrentChannel()->id)
            ->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_CLOSED, Order::STATUS_FRAUD])
            ->whereIn('id', $this->workflow->query()->select('order_id')->where('review', 'waiting'))
            ->latest('id')->paginate(20);

        return view('berkay::admin.workshop', compact('orders'));
    }

    public function advance(int $id, string $action): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('berkay.workshop'), 403);
        $order = Order::query()->where('channel_id', core()->getCurrentChannel()->id)->findOrFail($id);
        $this->workflow->advance($order, $action);

        return back()->with('success', trans('berkay::workflow.updated'));
    }
}
