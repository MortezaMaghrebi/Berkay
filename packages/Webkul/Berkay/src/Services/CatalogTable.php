<?php

namespace Webkul\Berkay\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Berkay\Repositories\VariantDetailsRepository;
use Webkul\Berkay\Support\ProductDiscount;
use Webkul\Product\Helpers\Indexers\Flat;
use Webkul\Product\Jobs\Search\IndexProducts;
use Webkul\Product\Jobs\UpdateCreateInventoryIndex;
use Webkul\Product\Jobs\UpdateCreatePriceIndex;
use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductInventoryRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Shop\Listeners\CatalogCache;

class CatalogTable
{
    public const MAX_PRICE = 99999999;

    public function __construct(
        protected ProductRepository $products,
        protected AttributeRepository $attributes,
        protected ProductInventoryRepository $inventory,
    ) {}

    public function parents(): Builder
    {
        return $this->products->getModel()->newQuery()->whereNull('parent_id')
            ->whereHas('channels', fn ($query) => $query->where('channels.id', core()->getCurrentChannel()->id));
    }

    public function colors()
    {
        return $this->attributes->findOneByField('code', 'color');
    }

    public function inventorySource(): ?int
    {
        return core()->getCurrentChannel()->inventory_sources->first()?->id;
    }

    public function save(array $rows): array
    {
        [$saved, $parents] = DB::transaction(function () use ($rows) {
            $parentIds = array_values(array_unique(array_column($rows, 'parent')));
            $parents = $this->parents()->whereKey($parentIds)->orderBy('products.id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($parents->count() === count($parentIds), 404);
            $saved = [];

            foreach ($rows as $row) {
                $parent = $parents[$row['parent']];
                $id = $row['id'] ?? null;

                if ($id) {
                    $product = $id === $parent->id ? $parent : $parent->variants()->whereKey($id)->firstOrFail();
                } else {
                    $product = $this->addColor($parent, $row);
                }

                $this->update($product, $row);
                $saved[] = ['key' => $row['key'], 'id' => $product->id, 'parent' => $parent->id, 'type' => $parent->type];
            }

            $stock = $this->parents()->whereKey($parentIds)
                ->with(['attribute_values', 'variants.attribute_values'])->get()
                ->mapWithKeys(fn ($parent) => [$parent->id => $parent->variants->concat([$parent])
                    ->mapWithKeys(fn ($product) => [$product->id => $this->unlimitedStock($product)])
                    ->all()]);
            foreach ($saved as $index => $result) {
                $saved[$index]['stock'] = $stock[$result['parent']];
            }

            return [$saved, $parentIds];
        }, 3);

        $this->refresh($parents);

        return $saved;
    }

    public function remove(int $parentId, int $id): void
    {
        DB::transaction(function () use ($parentId, $id) {
            $parent = $this->parents()->whereKey($parentId)->lockForUpdate()->firstOrFail();
            $product = $id === $parent->id ? $parent : $parent->variants()->whereKey($id)->firstOrFail();
            Event::dispatch('catalog.product.delete.before', $product->id);
            $this->products->delete($product->id);
            Event::dispatch('catalog.product.delete.after', $product->id);
        });

        if ($id !== $parentId) {
            $this->refresh([$parentId]);
        }
    }

    public function adjust(string $mode, float $value, string $scope): int
    {
        $updated = 0;
        $this->parents()->select('products.id')->chunkById(30, function ($batch) use ($mode, $value, $scope, &$updated) {
            $ids = $batch->modelKeys();
            $updated += DB::transaction(function () use ($ids, $mode, $value, $scope) {
                $parents = $this->parents()->whereKey($ids)->orderBy('products.id')->lockForUpdate()
                    ->with(['attribute_values', 'variants.attribute_values'])->get();
                $count = 0;

                foreach ($parents as $parent) {
                    $products = match ($scope) {
                        'variants' => $parent->variants,
                        'products' => collect([$parent]),
                        default => collect([$parent])->concat($parent->variants),
                    };

                    foreach ($products as $product) {
                        if (! $this->editablePrice($product)) {
                            continue;
                        }

                        $current = (float) $product->price;
                        $new = $mode === 'percent' ? $current * (1 + $value / 100) : $current + $value;
                        $new = round(max(0, min(self::MAX_PRICE, $new)), 2);

                        if ($new !== $current) {
                            $this->update($product, ['price' => $new]);
                            $count++;
                        }
                    }
                }

                return $count;
            }, 3);
            $this->refresh($ids);
        }, 'products.id', 'id');

        return $updated;
    }

    public function editablePrice(Product $product): bool
    {
        return $product->getEditableAttributes()->contains('code', 'price');
    }

    /** Check whether the catalog row represents stock or a configurable group of stock items. */
    public function supportsUnlimitedStock(Product $product): bool
    {
        return $product->type === 'configurable' || $product->getTypeInstance()->isInventoryManageable();
    }

    /** A configurable product is unlimited when all of its variants are unlimited. */
    public function unlimitedStock(Product $product): bool
    {
        if ($product->type === 'configurable') {
            return $product->variants->isNotEmpty() && $product->variants->every(fn ($variant) => ! $variant->manage_stock);
        }

        return ! $product->manage_stock;
    }

    private function addColor(Product $parent, array &$row): Product
    {
        $color = $this->colors();
        $option = $color?->options()->find($row['color'] ?? 0);

        if (
            ! $option
            || ! $parent->attribute_family->custom_attributes->contains('id', $color->id)
            || ! in_array($parent->type, ['simple', 'configurable'], true)
            || ($parent->type === 'configurable' && $parent->super_attributes()->pluck('attributes.id')->all() !== [$color->id])
        ) {
            throw ValidationException::withMessages(['color' => trans('berkay::table.color-unavailable')]);
        }

        if ($parent->variants()->with('attribute_values')->get()->contains(fn ($variant) => (int) $variant->color === $option->id)) {
            throw ValidationException::withMessages(['color' => trans('berkay::table.duplicate-color')]);
        }

        $row['price'] ??= (float) $parent->price;
        $row['quantity'] ??= $parent->type === 'simple' ? (int) $parent->inventory_source_qty($this->inventorySource()) : 0;
        $row['status'] ??= (bool) $parent->status;
        $row['unlimited_stock'] ??= $this->unlimitedStock($parent);

        if ($parent->type === 'simple') {
            $parent->forceFill(['type' => 'configurable'])->save();
            $parent->super_attributes()->attach($color->id);
        }

        $variant = $this->products->create([
            'type' => 'simple',
            'sku' => 'BK-'.Str::ulid(),
            'parent_id' => $parent->id,
            'attribute_family_id' => $parent->attribute_family_id,
        ]);

        $variant->channels()->sync($parent->channels()->pluck('channels.id'));
        $attributes = [
            'sku' => $variant->sku,
            'url_key' => strtolower($variant->sku),
            'name' => $row['name'] ?? $parent->name.' – '.$option->admin_name,
            'short_description' => $parent->short_description,
            'description' => $parent->description,
            'weight' => $parent->weight ?: 0.5,
            'status' => $row['status'],
            'visible_individually' => 0,
            'manage_stock' => (int) ! $row['unlimited_stock'],
            'color' => $option->id,
            'price' => (float) $row['price'] === 0.0 ? '0.00' : $row['price'],
        ];
        $this->products->update($attributes + $this->context(), $variant->id, array_keys($attributes));

        return $variant->fresh();
    }

    private function update(Product $product, array $row): void
    {
        $variantFields = array_intersect_key($row, array_flip(['discount', 'unlimited_stock']));
        if ($product->type === 'configurable' && $variantFields) {
            foreach ($product->variants()->with('attribute_values')->get() as $variant) {
                $this->update($variant, $variantFields);
            }
            unset($row['discount'], $row['unlimited_stock']);
        }

        $attributes = array_intersect_key($row, array_flip(['name', 'status']));
        if (isset($row['status'])) {
            $attributes['status'] = (int) $row['status'];
        }

        if (array_key_exists('unlimited_stock', $row) && $product->getTypeInstance()->isInventoryManageable()) {
            $attributes['manage_stock'] = (int) ! $row['unlimited_stock'];
        }

        if (array_key_exists('price', $row) || array_key_exists('discount', $row)) {
            if (! $this->editablePrice($product)) {
                throw ValidationException::withMessages(['price' => trans('berkay::table.variant-price')]);
            }

            $old = (float) $product->price;
            $price = $row['price'] ?? $old;
            $oldSale = (float) $product->special_price;
            $discount = $row['discount'] ?? ($old > 0 && $oldSale > 0 && $oldSale < $old ? (1 - $oldSale / $old) * 100 : 0);
            $attributes['price'] = (float) $price === 0.0 ? '0.00' : $price;
            $sale = ProductDiscount::price((float) $price, $discount);
            if (array_key_exists('discount', $row)) {
                $attributes['special_price_from'] = '';
                $attributes['special_price_to'] = '';
            }
            if (array_key_exists('discount', $row) || ($oldSale > 0 && $oldSale < $old)) {
                $attributes['special_price'] = $sale ?? '';
            }
        }

        if ($attributes) {
            $this->products->update($attributes + $this->context(), $product->id, array_keys($attributes));
        }

        if ($product->parent_id && array_intersect_key($row, array_flip(['color_description', 'material', 'feature1', 'feature2', 'feature3']))) {
            $details = $product->additional['berkay_variant'] ?? [];
            foreach (['color_description', 'material'] as $field) {
                $details[$field] = array_key_exists($field, $row) ? ($row[$field] ?? '') : ($details[$field] ?? '');
            }
            foreach ([1, 2, 3] as $index) {
                $field = 'feature'.$index;
                $details['features'][$index - 1] = array_key_exists($field, $row)
                    ? ($row[$field] ?? '')
                    : ($details['features'][$index - 1] ?? '');
            }
            app(VariantDetailsRepository::class)->saveDetails($product->id, [
                'name' => $row['name'] ?? $product->name,
                'berkay_details' => $details,
            ]);
        }

        if (isset($row['quantity']) && ! $product->getTypeInstance()->isComposite()) {
            $source = $this->inventorySource();
            if (! $source) {
                throw ValidationException::withMessages(['quantity' => trans('berkay::app.seller.inventory-required')]);
            }
            $this->inventory->saveInventories(['inventories' => [$source => $row['quantity']]], $product);
        }
    }

    private function context(): array
    {
        return ['channel' => core()->getCurrentChannel()->code, 'locale' => core()->getCurrentLocale()->code];
    }

    private function refresh(array $parents): void
    {
        $products = $this->parents()->whereKey($parents)->with('variants')->get();
        $ids = $products->flatMap(fn ($product) => [...$product->variants->modelKeys(), $product->id])->all();

        if (! $ids) {
            return;
        }

        app(Flat::class)->reindexBatch($products);
        (new UpdateCreateInventoryIndex($ids))->handle();
        (new UpdateCreatePriceIndex($ids))->handle();
        IndexProducts::dispatch($ids);
        app(CatalogCache::class)->flush();
    }
}
