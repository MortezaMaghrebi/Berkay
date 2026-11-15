<?php

namespace Webkul\Shop\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Core\Rules\PhoneNumber;
use Webkul\Core\Rules\PostCode;
use Webkul\Core\Rules\StateBelongsToCountry;
use Webkul\Customer\Rules\VatIdRule;

class AddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'company_name' => ['nullable'],
            'first_name' => ['required'],
            'last_name' => ['required'],
            'address' => ['bail', 'required', 'array', 'min:1', function ($attribute, $value, $fail) {
                if (mb_strlen(implode(PHP_EOL, array_filter($value, 'is_string'))) > 255) {
                    $fail('validation.max.string')->translate(['max' => 255]);
                }
            }],
            'address.0' => ['required_with:address', 'string', 'max:255'],
            'address.*' => ['nullable', 'string', 'max:255'],
            'country' => core()->isCountryRequired() ? ['required'] : ['nullable'],
            'state' => [
                core()->isStateRequired() ? 'required' : 'nullable',
                new StateBelongsToCountry($this->input('country')),
            ],
            'city' => ['required', 'string'],
            'postcode' => [core()->isPostCodeRequired() ? 'required' : 'nullable', 'string', 'max:255', new PostCode],
            'phone' => ['required', new PhoneNumber],
            'vat_id' => [(new VatIdRule)->setCountry($this->input('country'))],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * Normalize numerals in the phone and postal code.
     */
    protected function prepareForValidation(): void
    {
        foreach (['phone', 'postcode'] as $field) {
            if (is_string($value = $this->input($field))) {
                $this->merge([$field => KavenegarSms::westernDigits(trim($value))]);
            }
        }
    }

    /**
     * Attributes.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'address.*' => 'address',
        ];
    }
}
