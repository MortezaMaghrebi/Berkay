<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.users.'.($account ? 'edit' : 'new-staff')) }}</x-slot>
    <section class="bk-admin bk-seller-editor">
        <div class="bk-toolbar"><h1>{{ trans('berkay::app.users.'.($account ? 'edit' : 'new-staff')) }}</h1><a href="{{ route('admin.berkay.users.index', ['kind' => $kind]) }}">{{ trans('berkay::app.back') }}</a></div>
        @include('berkay::admin.seller-feedback')
        <form method="post" class="bk-form" action="{{ $account ? route('admin.berkay.users.update', ['kind' => $kind, 'id' => $account->id]) : route('admin.berkay.users.store') }}">
            @csrf
            @if ($account) @method('PUT') @endif
            <div class="bk-form-section">
                <h2>{{ trans('berkay::app.users.profile') }}</h2>
                @foreach ($kind === 'staff' ? ['name'] : ['first_name', 'last_name'] as $field)
                    <label for="{{ $field }}">{{ trans('berkay::app.users.'.str_replace('_', '-', $field)) }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $account?->$field) }}" required maxlength="100" autocomplete="off">
                @endforeach
                <label for="email">{{ trans('berkay::app.users.email') }}</label>
                <input type="email" id="email" name="email" dir="ltr" value="{{ old('email', $account?->email) }}" required maxlength="255" autocomplete="off">
                @if ($kind === 'customers')
                    <label for="phone">{{ trans('berkay::app.users.phone') }}</label>
                    <input type="tel" id="phone" name="phone" dir="ltr" value="{{ old('phone', $account?->phone) }}" maxlength="30">
                @endif
                <label for="status">{{ trans('berkay::app.users.status') }}</label>
                <select id="status" name="status"><option value="1" @selected(old('status', $account?->status ?? 1) == 1)>{{ trans('berkay::app.users.active') }}</option><option value="0" @selected(old('status', $account?->status ?? 1) == 0)>{{ trans('berkay::app.users.inactive') }}</option></select>
            </div>
            <div class="bk-form-section">
                <h2>{{ trans('berkay::app.users.password') }}</h2>
                <p class="bk-help" id="password-help">{{ trans('berkay::app.users.password-help') }}</p>
                <label for="password">{{ trans('berkay::app.users.new-password') }}</label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" aria-describedby="password-help" @required(!$account)>
                <label for="password_confirmation">{{ trans('berkay::app.users.confirm-password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="128" @required(!$account)>
            </div>
            <div class="bk-form-section">
                <h2>{{ trans('berkay::app.users.access') }}</h2>
                @if ($kind === 'customers' && $staff)
                    <p class="bk-help">{{ trans('berkay::app.users.existing-help') }}</p>
                    <a href="{{ route('admin.berkay.users.edit', ['kind' => 'staff', 'id' => $staff->id]) }}">{{ trans('berkay::app.users.edit-staff') }}</a>
                @else
                    @if ($kind === 'customers')
                        <input type="hidden" name="grant_admin" value="0">
                        <label><input type="checkbox" name="grant_admin" value="1" @checked(old('grant_admin'))> {{ trans('berkay::app.users.grant') }}</label>
                        <p class="bk-help">{{ trans('berkay::app.users.grant-help') }}</p>
                    @endif
                    <label for="role_id">{{ trans('berkay::app.users.role') }}</label>
                    <select id="role_id" name="role_id" @required($kind === 'staff')>
                        <option value="">{{ trans('berkay::app.users.choose-role') }}</option>
                        @foreach ($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id', $kind === 'staff' ? $account?->role_id : null) == $role->id)>{{ $role->name }} — {{ trans('berkay::app.users.'.($role->permission_type === 'all' ? 'full-access' : 'limited-access')) }}</option>@endforeach
                    </select>
                    <p class="bk-help">{{ trans('berkay::app.users.role-help') }}</p>
                @endif
            </div>
            <button type="submit">{{ trans('berkay::app.save') }}</button>
        </form>
    </section>
</x-admin::layouts>
