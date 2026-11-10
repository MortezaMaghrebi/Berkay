<?php

namespace Webkul\Berkay\Repositories;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Category\Repositories\CategoryRepository;

class SellerCategoryRepository extends CategoryRepository
{
    /** Include hidden categories when managing this store's tree. */
    public function storeTree(): mixed
    {
        $root = core()->getCurrentChannel()->root_category_id;

        return $this->model->newQuery()->orderBy('position')->descendantsAndSelf($root)->toTree($root);
    }

    /** Scope category identifiers to the store's root. */
    public function storeIds(): array
    {
        return $this->model->newQuery()->descendantsAndSelf(core()->getCurrentChannel()->root_category_id)->pluck('id')->all();
    }

    /** Resolve a category while protecting the store root from editing. */
    public function storeCategory(int $id): mixed
    {
        abort_unless(
            in_array($id, $this->storeIds())
            && $id !== core()->getCurrentChannel()->root_category_id,
            404
        );

        return $this->findOrFail($id);
    }

    /** Build a readable category with automatic addresses and ordering. */
    public function saveSimple(array $data, ?int $id = null): mixed
    {
        $category = $id ? $this->storeCategory($id) : null;
        $parent = (int) (($data['parent_id'] ?? null) ?: core()->getCurrentChannel()->root_category_id);
        if (! in_array($parent, $this->storeIds())) {
            throw ValidationException::withMessages(['parent_id' => trans('berkay::app.seller.invalid-category')]);
        }
        if (
            $category
            && ($parent === $category->id || $category->descendants()->whereKey($parent)->exists())
        ) {
            throw ValidationException::withMessages(['parent_id' => trans('berkay::app.seller.category-cycle')]);
        }
        $values = [
            'name' => $data['name'], 'parent_id' => $parent, 'status' => $data['status'],
            'locale' => core()->getCurrentLocale()->code,
            'slug' => $category?->slug ?: 'collection-'.strtolower((string) Str::ulid()),
            'position' => $category?->position ?? ((int) $this->model->newQuery()->where('parent_id', $parent)->max('position') + 1),
            'display_mode' => $category?->display_mode ?: 'products_and_description',
            'meta_title' => $category?->meta_title ?: $data['name'],
            'meta_description' => $category?->meta_description ?: $data['name'],
        ];
        $values[core()->getCurrentLocale()->code] = array_intersect_key($values, array_flip(['name', 'slug', 'meta_title', 'meta_description']));
        if ($category) {
            $values['logo_path'] = [$category->logo_path];
            $values['banner_path'] = [$category->banner_path];
        }
        $saved = $category ? parent::update($values, $id) : parent::create($values);
        if (! empty($data['category_logo'])) {
            $previous = $saved->logo_path;
            $directory = 'categories/'.$saved->id.'/';
            $path = $directory.Str::ulid().'.webp';
            if (! Storage::put($path, (string) image_manager()->fromUpload($data['category_logo'])->toWebp()->toBytes())) {
                throw ValidationException::withMessages(['category_logo' => trans('berkay::app.seller.image-save-failed')]);
            }

            try {
                $saved->forceFill(['logo_path' => $path])->saveOrFail();
            } catch (\Throwable $exception) {
                Storage::delete($path);

                throw $exception;
            }

            if ($previous && Str::startsWith($previous, $directory)) {
                Storage::delete($previous);
            }
        }

        return $saved;
    }
}
