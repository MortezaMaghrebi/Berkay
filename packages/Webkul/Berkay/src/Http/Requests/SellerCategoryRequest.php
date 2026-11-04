<?php

namespace Webkul\Berkay\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellerCategoryRequest extends FormRequest
{
    /** Require explicit category creation or edit access. */
    public function authorize(): bool
    {
        return bouncer()->hasPermission('catalog.categories.'.($this->route('id') ? 'edit' : 'create'));
    }

    /** Validate only the category's everyday fields. */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer'],
            'status' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /** Give validation errors familiar Persian field names. */
    public function attributes(): array
    {
        return [
            'name' => trans('berkay::app.seller.category-name'),
            'parent_id' => trans('berkay::app.seller.parent-category'),
            'status' => trans('berkay::app.seller.status'),
            'image' => trans('berkay::app.seller.category-photo'),
        ];
    }
}
