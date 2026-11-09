<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.users.'.($account ? 'edit' : 'new-staff')) }}</x-slot>
    <section class="bk-admin bk-seller-editor">
        <div class="bk-toolbar">
            <h1>{{ trans('berkay::app.users.'.($account ? 'edit' : 'new-staff')) }}</h1>
            <a href="{{ route('admin.berkay.users.index', ['kind' => $kind]) }}">{{ trans('berkay::app.back') }}</a>
        </div>
        @include('berkay::admin.seller-feedback')
        <form method="post" class="bk-form" action="{{ $account ? route('admin.berkay.users.update', ['kind' => $kind, 'id' => $account->id]) : route('admin.berkay.users.store') }}">
            @csrf
            @if ($account)
                @method('PUT')
            @endif

            @foreach ($kind === 'staff' ? ['name'] : ['first_name', 'last_name'] as $field)
                <label for="{{ $field }}">{{ trans('berkay::app.users.'.str_replace('_', '-', $field)) }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $account?->$field) }}" required maxlength="100">
            @endforeach

            @if ($kind === 'staff')
                <label for="email">{{ trans('berkay::app.users.email') }}</label>
                <input type="email" id="email" name="email" dir="ltr" value="{{ old('email', $account?->email) }}" required maxlength="255">
            @else
                <label for="phone">{{ trans('berkay::sms.phone') }}</label>
                <input type="tel" id="phone" name="phone" dir="ltr" value="{{ old('phone', $account?->phone) }}" maxlength="32" required>
                <p class="bk-help">{{ trans('berkay::sms.admin-phone-help') }}</p>
            @endif

            <label for="status">{{ trans('berkay::app.users.status') }}</label>
            <select id="status" name="status">
                <option value="1" @selected(old('status', $account?->status ?? 1) == 1)>{{ trans('berkay::app.users.active') }}</option>
                <option value="0" @selected(old('status', $account?->status ?? 1) == 0)>{{ trans('berkay::app.users.inactive') }}</option>
            </select>

            @if ($kind === 'customers')
                @if ($staff)
                    <a href="{{ route('admin.berkay.users.edit', ['kind' => 'staff', 'id' => $staff->id]) }}">{{ trans('berkay::app.users.edit-staff') }}</a>
                @else
                    <details @if(old('grant_admin')) open @endif>
                        <summary>{{ trans('berkay::app.users.grant') }}</summary>
                        <label><input type="checkbox" name="grant_admin" value="1" @checked(old('grant_admin'))> {{ trans('berkay::app.users.grant') }}</label>
                        <label for="staff_email">{{ trans('berkay::sms.staff-email') }}</label>
                        <input type="email" id="staff_email" name="staff_email" dir="ltr" value="{{ old('staff_email', $account?->email) }}" maxlength="255">
                        @include('berkay::admin.users.staff-fields')
                    </details>
                @endif
            @else
                @include('berkay::admin.users.staff-fields')
            @endif
            <button type="submit">{{ trans('berkay::app.save') }}</button>
        </form>
    </section>
</x-admin::layouts>
