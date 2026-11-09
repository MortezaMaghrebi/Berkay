<x-shop::layouts.account>
    <x-slot:title>@lang('shop::app.customers.account.profile.index.title')</x-slot>

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <section class="flex-auto mx-4 max-md:mx-6 max-sm:mx-4">
        <div class="bk-profile-logout">
            <form method="post" action="{{ route('shop.customer.session.destroy') }}">
                @csrf
                @method('DELETE')
                <button type="submit">@lang('shop::app.components.layouts.header.mobile.logout')</button>
            </form>
        </div>
        <h1 class="text-2xl font-medium">@lang('shop::app.customers.account.profile.index.title')</h1>
        @if ($customer->image_url)
            <img class="mt-4 size-20 rounded-full object-cover" src="{{ $customer->image_url }}" alt="" width="80" height="80">
        @endif
        <dl class="mt-5 grid gap-2">
            <dt>{{ trans('berkay::app.users.first-name') }} / {{ trans('berkay::app.users.last-name') }}</dt>
            <dd class="font-semibold">{{ $customer->first_name }} {{ $customer->last_name }}</dd>
            <dt class="mt-3">{{ trans('berkay::sms.phone') }}</dt>
            <dd class="font-semibold"><bdi dir="ltr">{{ $customer->phone ?: '—' }}</bdi></dd>
        </dl>
        <div class="mt-6 flex flex-wrap gap-3">
            <a class="secondary-button" href="{{ route('shop.customers.account.profile.edit') }}">@lang('shop::app.customers.account.profile.index.edit')</a>
            <a class="secondary-button" href="{{ route('shop.berkay.phone.settings', 'change') }}">{{ trans('berkay::sms.change') }}</a>
            @if ($customer->phone)
                <a class="secondary-button" href="{{ route('shop.berkay.phone.settings', 'delete') }}">{{ trans('berkay::sms.delete') }}</a>
            @endif
        </div>
    </section>
</x-shop::layouts.account>
