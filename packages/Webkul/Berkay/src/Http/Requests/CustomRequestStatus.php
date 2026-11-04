<?php

namespace Webkul\Berkay\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomRequestStatus extends FormRequest
{
    /** Require permission to change bespoke order records. */
    public function authorize(): bool
    {
        return bouncer()->hasPermission('berkay.custom-orders.edit');
    }

    /** Restrict staff-editable fields and valid workflow states. */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['new', 'reviewing', 'quoted', 'accepted', 'closed'])],
            'admin_notes' => ['nullable', 'string', 'max:6000'],
        ];
    }
}
