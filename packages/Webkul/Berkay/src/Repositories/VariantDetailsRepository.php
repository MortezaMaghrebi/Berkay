<?php

namespace Webkul\Berkay\Repositories;

use Webkul\Product\Repositories\ProductRepository;

class VariantDetailsRepository extends ProductRepository
{
    /** Preserve optional variant presentation data alongside native product metadata. */
    public function saveDetails(int $id, array $data): void
    {
        if (! array_key_exists('berkay_details', $data)) {
            return;
        }
        $product = $this->findOrFail($id);
        $details = $data['berkay_details'];
        $additional = $product->additional ?? [];
        $additional['berkay_variant'] = [
            'name' => $data['name'] ?? $product->name,
            'color_description' => trim($details['color_description'] ?? ''),
            'material' => trim($details['material'] ?? ''),
            'features' => array_values(array_filter(array_slice($details['features'] ?? [], 0, 3), fn ($value) => trim((string) $value) !== '')),
        ];
        $this->getModel()->newQuery()->whereKey($id)->update(['additional' => json_encode($additional, JSON_UNESCAPED_UNICODE)]);
    }
}
