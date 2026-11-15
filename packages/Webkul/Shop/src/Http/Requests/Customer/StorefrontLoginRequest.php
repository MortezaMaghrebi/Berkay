<?php

namespace Webkul\Shop\Http\Requests\Customer;

use Webkul\Customer\Facades\Captcha;

class StorefrontLoginRequest extends LoginRequest
{
    /**
     * Validate a password sign-in using an email address or mobile number.
     */
    public function rules(): array
    {
        return Captcha::getValidations([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
    }

    /**
     * Reopen the password tab after validation errors.
     */
    protected function getRedirectUrl(): string
    {
        return route('shop.customer.session.index', ['tab' => 'password']);
    }
}
