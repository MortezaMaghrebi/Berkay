<?php

namespace Webkul\Berkay\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use Webkul\Berkay\Services\KavenegarSms;

class CustomRequest extends FormRequest
{
    /** Accept public inquiries only when the store enables them. */
    public function authorize(): bool
    {
        return (bool) core()->getConfigData('berkay.store.identity.custom_orders');
    }

    /** Bound public inquiry fields and require the spam trap to be empty. */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^09[0-9]{9}$/'],
            'product_type' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:15', 'max:4000'],
            'website' => ['nullable', 'max:0'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    /** Normalize Persian digits and country codes before validation. */
    protected function prepareForValidation(): void
    {
        try {
            $this->merge(['phone' => app(KavenegarSms::class)->normalizePhone((string) $this->input('phone'))]);
        } catch (InvalidArgumentException) {
            $this->merge(['phone' => '']);
        }
    }
}
