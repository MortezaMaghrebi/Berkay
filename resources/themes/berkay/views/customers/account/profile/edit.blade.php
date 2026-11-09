<x-shop::layouts.account>
    <x-slot:title>@lang('shop::app.customers.account.profile.edit.edit-profile')</x-slot>

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <section class="flex-auto mx-4 max-md:mx-6 max-sm:mx-4">
        <h1 class="text-2xl font-medium">@lang('shop::app.customers.account.profile.edit.edit-profile')</h1>
        @if ($errors->any())
            <ul class="bk-error mt-4" role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        <form method="post" class="bk-form mt-4" enctype="multipart/form-data" action="{{ route('shop.customers.account.profile.update') }}">
            @csrf
            <label for="first_name">{{ trans('berkay::app.users.first-name') }}</label>
            <input id="first_name" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required maxlength="100" autocomplete="given-name">
            <label for="last_name">{{ trans('berkay::app.users.last-name') }}</label>
            <input id="last_name" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required maxlength="100" autocomplete="family-name">
            <details>
                <summary>{{ trans('berkay::sms.profile-optional') }}</summary>
                <label for="gender">@lang('shop::app.customers.account.profile.edit.gender')</label>
                <select id="gender" name="gender">
                    <option value="">—</option>
                    @foreach (['Other', 'Male', 'Female'] as $gender)
                        <option value="{{ $gender }}" @selected(old('gender', $customer->gender) === $gender)>@lang('shop::app.customers.account.profile.edit.'.strtolower($gender))</option>
                    @endforeach
                </select>
                <label for="date_of_birth">@lang('shop::app.customers.account.profile.edit.dob')</label>
                <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer->date_of_birth?->format('Y-m-d')) }}">
                <label for="image">{{ trans('berkay::sms.profile-photo') }}</label>
                <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </details>
            <button type="submit" class="bk-button">{{ trans('berkay::app.save') }}</button>
        </form>
        <a class="mt-5 inline-flex min-h-11 items-center" href="{{ route('shop.customers.account.profile.index') }}">{{ trans('berkay::app.back') }}</a>
    </section>
</x-shop::layouts.account>
