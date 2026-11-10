<?php

namespace Webkul\Admin\Http\Controllers\Sales;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Sales\OrderShipmentDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Berkay\Services\ShipmentQuantity;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\ShipmentRepository;

class ShipmentController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected ShipmentQuantity $quantity,
        protected ShipmentRepository $shipmentRepository
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(OrderShipmentDataGrid::class)->process();
        }

        return view('admin::sales.shipments.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create(int $orderId)
    {
        $order = $this->orderRepository->findOrFail($orderId);

        if (! $order->channel || ! $order->canShip()) {
            session()->flash('error', trans('admin::app.sales.shipments.create.creation-error'));

            return redirect()->back();
        }

        return view('admin::sales.shipments.create', compact('order'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(int $orderId)
    {
        $order = $this->orderRepository->findOrFail($orderId);

        if (! $order->canShip()) {
            session()->flash('error', trans('admin::app.sales.shipments.create.order-error'));

            return redirect()->back();
        }

        $this->validate(request(), [
            'shipment.source' => 'required|integer',
            'shipment.items' => 'sometimes|array',
            'shipment.items.*' => 'array',
            'shipment.items.*.*' => 'required|integer|min:0',
        ]);

        $data = request()->only(['shipment', 'carrier_name']);

        if (! $this->itemsBelongToOrder($order, array_keys((array) ($data['shipment']['items'] ?? [])))) {
            session()->flash('error', trans('admin::app.sales.shipments.create.quantity-invalid'));

            return redirect()->back();
        }

        if (
            ! $order->channel?->inventory_sources->where('status', 1)->contains('id', (int) $data['shipment']['source'])
            || ! $this->isInventoryValidate($data, $order)
        ) {
            session()->flash('error', trans('admin::app.sales.shipments.create.quantity-invalid'));

            return redirect()->back();
        }

        $this->shipmentRepository->create(array_merge($data, [
            'order_id' => $orderId,
        ]));

        session()->flash('success', trans('admin::app.sales.shipments.create.success'));

        return redirect()->route('admin.sales.orders.view', $orderId);
    }

    /** Keep only positive quantities that can ship from the selected source. */
    protected function isInventoryValidate(array &$data, Order $order): bool
    {
        $sourceId = (int) $data['shipment']['source'];
        $items = $order->items->keyBy('id');
        $selected = [];

        foreach ($data['shipment']['items'] ?? [] as $itemId => $sources) {
            $quantity = (int) ($sources[$sourceId] ?? 0);

            if ($quantity === 0) {
                continue;
            }

            if ($quantity > $this->quantity->maximum($items[$itemId], $sourceId)) {
                return false;
            }

            $selected[$itemId] = [$sourceId => $quantity];
        }

        $data['shipment']['items'] = $selected;

        return $selected !== [];
    }

    /**
     * Show the view for the specified resource.
     *
     * @return View
     */
    public function view(int $id)
    {
        $shipment = $this->shipmentRepository->findOrFail($id);

        return view('admin::sales.shipments.view', compact('shipment'));
    }

    /**
     * Whether every item the request names is one the order carries.
     */
    protected function itemsBelongToOrder($order, array $itemIds): bool
    {
        return ! array_diff(
            $itemIds,
            $order->items->pluck('id')->all()
        );
    }
}
