<?php

namespace Webkul\Berkay\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webkul\Berkay\Repositories\SellerCategoryRepository;

class SellerProductRequest extends FormRequest
{
    /** Require the create or edit permission for the requested operation. */
    public function authorize(): bool
    {
        return bouncer()->hasPermission('catalog.products.'.($this->route('id') ? 'edit' : 'create'));
    }

    /** Validate a short product form with scoped categories and bounded image uploads. */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'category_id' => ['required', 'integer', Rule::in(app(SellerCategoryRepository::class)->storeIds())],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'special_price' => ['nullable', 'numeric', 'gt:0', 'lt:price'],
            'images' => [$this->route('id') ? 'sometimes' : 'required', 'array', 'min:1', 'max:8'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /** Translate labels in validation messages. */
    public function attributes(): array
    {
        return ['special_price' => trans('berkay::collections.special-price')] + collect(['name', 'price', 'quantity', 'category_id', 'description', 'images', 'status'])
            ->mapWithKeys(fn ($field) => [$field => trans('berkay::app.seller.'.$field)])->all();
    }

    /** Accept Persian and Arabic digits in price and quantity fields. */
    protected function prepareForValidation(): void
    {
        $digits = array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789'));
        foreach (['price', 'quantity', 'special_price'] as $field) {
            if (! $this->has($field) || ! is_scalar($this->input($field))) {
                continue;
            }

            $value = str_replace([',', '٬'], '', strtr((string) $this->input($field), $digits));
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }
}
