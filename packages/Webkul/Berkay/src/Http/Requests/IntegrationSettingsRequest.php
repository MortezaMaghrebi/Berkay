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
            'torob_enabled' => ['required', 'boolean'],
        ];
    }

    /** Keep credentials out of flashed input when another field fails validation. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->back()->withErrors($validator)
                ->withInput($this->except(['zarinpal_merchant_id']))
        );
    }
}
