<?php

namespace Webkul\Berkay\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IntegrationSettingsRequest extends FormRequest
{
    /** Restrict credentials and payment activation to explicitly authorized staff. */
    public function authorize(): bool
    {
        return bouncer()->hasPermission('berkay.integrations.edit');
    }

    /** Validate provider identifiers without accepting arbitrary configuration paths. */
    public function rules(): array
    {
        return [
            'zarinpal_active' => ['required', 'boolean'],
            'zarinpal_sandbox' => ['required', 'boolean'],
            'zarinpal_merchant_id' => ['nullable', 'uuid'],
            'kavenegar_enabled' => ['required', 'boolean'],
            'kavenegar_api_key' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{16,512}$/'],
            'kavenegar_order_template' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'kavenegar_status_template' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'torob_enabled' => ['required', 'boolean'],
        ];
    }

    /** Keep credentials out of flashed input when another field fails validation. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->back()->withErrors($validator)
                ->withInput($this->except(['zarinpal_merchant_id', 'kavenegar_api_key']))
        );
    }
}
