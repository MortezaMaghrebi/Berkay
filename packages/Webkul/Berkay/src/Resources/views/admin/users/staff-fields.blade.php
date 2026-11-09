<label for="password">{{ trans('berkay::app.users.new-password') }}</label>
<input type="password" id="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" @required(!$account)>
<label for="password_confirmation">{{ trans('berkay::app.users.confirm-password') }}</label>
<input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="128" @required(!$account)>
<label for="role_id">{{ trans('berkay::app.users.role') }}</label>
<select id="role_id" name="role_id" @required($kind === 'staff')>
    <option value="">{{ trans('berkay::app.users.choose-role') }}</option>
    @foreach ($roles as $role)
        <option value="{{ $role->id }}" @selected(old('role_id', $kind === 'staff' ? $account?->role_id : null) == $role->id)>{{ $role->name }}</option>
    @endforeach
</select>
