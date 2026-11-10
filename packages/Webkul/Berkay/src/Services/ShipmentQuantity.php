<?php

namespace Webkul\Berkay\Services;

use Webkul\RMA\Helpers\Helper;
use Webkul\Sales\Models\OrderItem;

class ShipmentQuantity
{
    public function __construct(protected Helper $rma) {}

    public function maximum(OrderItem $item, int $sourceId): int
    {
        $maximum = min($item->qty_to_ship, $this->rma->getRemainingQtyAfterRMA($item->id)['qty']);

        if (! $item->product || $maximum <= 0) {
            return 0;
        }

        if (! $item->getTypeInstance()->isComposite()) {
            return $item->product->manage_stock
                ? max(0, min((int) $maximum, (int) $item->product->inventory_source_qty($sourceId)))
                : (int) $maximum;
        }

        foreach ($item->children as $child) {
            if (! $child->product) {
                return 0;
            }

            if (! $child->qty_ordered) {
                continue;
            }

            $units = $child->qty_ordered / $item->qty_ordered;
            $maximum = min($maximum, $child->qty_to_ship / $units);

            if ($child->product->manage_stock) {
                $maximum = min($maximum, $child->product->inventory_source_qty($sourceId) / $units);
            }
        }

        return max(0, (int) floor($maximum));
    }
}
