<?php

namespace Webkul\Berkay\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Berkay\Services\SmsTemplates;

class KavenegarSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return bouncer()->hasPermission('berkay.integrations.edit');
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{16,512}$/'],
            'templates' => ['nullable', 'array:'.implode(',', SmsTemplates::EVENTS)],
            'notifications' => ['sometimes', 'array:'.implode(',', SmsTemplates::NOTIFICATION_EVENTS)],
            'admin_recipient' => ['sometimes', 'nullable', 'string', 'max:32', function ($attribute, $value, $fail) {
                try {
                    app(KavenegarSms::class)->normalizePhone($value);
                } catch (InvalidArgumentException) {
                    $fail(trans('berkay::sms.admin-phone-invalid'));
                }
            }],
            'notifications.*' => ['required', 'boolean'],
            'templates.*' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(redirect()->back()->withErrors($validator)->withInput($this->except('api_key')));
    }
}
