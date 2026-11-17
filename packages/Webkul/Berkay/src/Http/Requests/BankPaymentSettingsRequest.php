<?php

namespace Webkul\Berkay\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BankPaymentSettingsRequest extends FormRequest
{
    /** Restrict provider and transfer settings to authorized administrators. */
    public function authorize(): bool
    {
        return bouncer()->hasPermission('berkay.integrations.edit');
    }

    /** Validate fields against the selected terminal's contract. */
    public function rules(): array
    {
        return match ($this->route('provider')) {
            'mellat' => [
                'enabled' => ['required', 'boolean'],
                'terminal_id' => ['nullable', 'regex:/^(?=[0-9]*[1-9])[0-9]{1,15}$/D'],
                'username' => ['nullable', 'string', 'max:128'],
                'password' => ['nullable', 'string', 'max:128'],
            ],
            'irankish' => [
                'enabled' => ['required', 'boolean'],
                'terminal_id' => ['nullable', 'regex:/^[0-9]{8}$/D'],
                'acceptor_id' => ['nullable', 'regex:/^[0-9]{15}$/D'],
                'password' => ['nullable', 'regex:/^[A-Fa-f0-9]{16}$/D'],
                'public_key' => ['nullable', 'string', 'max:4096', function ($attribute, $value, $fail) {
                    $key = str_starts_with($value, '-----BEGIN PUBLIC KEY-----')
                        ? openssl_pkey_get_public($value)
                        : false;

                    if (! $key || openssl_pkey_get_details($key)['type'] !== OPENSSL_KEYTYPE_RSA) {
                        $fail(trans('validation.regex', ['attribute' => 'PEM']));
                    }
                }],
            ],
            'moneytransfer' => ['instructions' => ['present', 'nullable', 'string', 'max:1500']],
            'torobpay' => [
                'enabled' => ['required', 'boolean'],
                'client_id' => ['nullable', 'string', 'max:128', 'regex:/^[^:\\x00-\\x20]+$/D'],
                'client_secret' => ['nullable', 'string', 'max:128'],
                'username' => ['nullable', 'string', 'max:128'],
                'password' => ['nullable', 'string', 'max:128'],
            ],
        };
    }

    /** Redirect with field errors while keeping terminal credentials out of the session. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(redirect()->back()->withErrors($validator, $this->route('provider')));
    }
}
