<x-shop::layouts.account>
    <x-slot:title>@lang('shop::app.customers.login-form.password')</x-slot>

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <section class="mx-4 min-w-0 flex-auto max-md:mx-6 max-sm:mx-4">
        <h1 class="text-2xl font-medium">@lang('shop::app.customers.login-form.password')</h1>

        <div class="mt-5 max-w-md">
            <x-shop::form :action="route('shop.customers.account.profile.password.update')">
                @if ($hasPassword)
                    <x-shop::form.control-group>
                        <x-shop::form.control-group.label for="current_password" class="required">
                            @lang('shop::app.customers.account.profile.edit.current-password')
                        </x-shop::form.control-group.label>

                        <x-shop::form.control-group.control
                            type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            rules="required"
                            :label="trans('shop::app.customers.account.profile.edit.current-password')"
                        />

                        <x-shop::form.control-group.error control-name="current_password" />
                    </x-shop::form.control-group>
                @endif

                <x-shop::form.control-group>
                    <x-shop::form.control-group.label for="password" class="required">
                        @lang('shop::app.customers.account.profile.edit.new-password')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        rules="required|min:8|max:72"
                        :label="trans('shop::app.customers.account.profile.edit.new-password')"
                    />

                    <x-shop::form.control-group.error control-name="password" />
                </x-shop::form.control-group>

                <x-shop::form.control-group>
                    <x-shop::form.control-group.label for="password_confirmation" class="required">
                        @lang('shop::app.customers.account.profile.edit.confirm-password')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        rules="required|confirmed:@password"
                        :label="trans('shop::app.customers.account.profile.edit.confirm-password')"
                    />

                    <x-shop::form.control-group.error control-name="password_confirmation" />
                </x-shop::form.control-group>

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="primary-button min-h-11">
                        @lang('shop::app.customers.account.profile.edit.save')
                    </button>

                    <a class="inline-flex min-h-11 items-center" href="{{ route('shop.customers.account.profile.index') }}">
                        {{ trans('berkay::app.back') }}
                    </a>
                </div>
            </x-shop::form>
        </div>
    </section>
</x-shop::layouts.account>
