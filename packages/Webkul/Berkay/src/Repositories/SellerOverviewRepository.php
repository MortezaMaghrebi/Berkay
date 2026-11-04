<?php

namespace Webkul\Berkay\Repositories;

use Webkul\Sales\Repositories\OrderRepository;

class SellerOverviewRepository extends OrderRepository
{
    /** Count real order states for the active store without loading customer data. */
    public function orderCounts(): array
    {
        $counts = $this->model->newQuery()
            ->where('channel_id', core()->getCurrentChannel()->id)
            ->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return collect(['pending', 'processing', 'completed'])->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all();
    }
}
