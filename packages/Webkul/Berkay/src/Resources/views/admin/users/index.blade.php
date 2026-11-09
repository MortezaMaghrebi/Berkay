<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.users.title') }}</x-slot>
    <section class="bk-admin bk-users-screen">
        <div class="bk-toolbar"><h1>{{ trans('berkay::app.users.title') }}</h1><a href="{{ route('admin.berkay.users.create') }}">{{ trans('berkay::app.users.new-staff') }}</a></div>
        <p class="bk-help">{{ trans('berkay::app.users.intro') }}</p>
        @include('berkay::admin.seller-feedback')
        <nav class="bk-users-tabs" aria-label="{{ trans('berkay::app.users.title') }}">
            @foreach (['customers', 'staff'] as $tab)
                <a href="{{ route('admin.berkay.users.index', ['kind' => $tab]) }}" @if($kind === $tab) aria-current="page" @endif>{{ trans('berkay::app.users.'.$tab) }}</a>
            @endforeach
        </nav>
        <form method="get" class="bk-form">
            <input type="hidden" name="kind" value="{{ $kind }}">
            <label for="user-search">{{ $kind === 'customers' ? trans('berkay::sms.search-customer') : trans('berkay::app.users.search') }}</label>
            <div class="bk-user-search"><input id="user-search" name="q" value="{{ request('q') }}" maxlength="100"><button type="submit">{{ trans('berkay::app.users.search-action') }}</button></div>
        </form>
        <div class="bk-users-list">
            @forelse ($accounts as $account)
                <article class="bk-form-section bk-toolbar">
                    <div><h2>{{ $kind === 'staff' ? $account->name : trim($account->first_name.' '.$account->last_name) }}</h2><p dir="ltr">{{ $kind === 'customers' ? ($account->phone ?: '—') : $account->email }}</p>@if ($kind === 'staff')<p>{{ $account->role?->name }}</p>@endif<small>{{ trans('berkay::app.users.'.($account->status ? 'active' : 'inactive')) }}</small></div>
                    <a href="{{ route('admin.berkay.users.edit', ['kind' => $kind, 'id' => $account->id]) }}">{{ trans('berkay::app.users.edit') }}</a>
                </article>
            @empty
                <p class="bk-help">{{ trans('berkay::app.users.empty') }}</p>
            @endforelse
        </div>
        {{ $accounts->links() }}
    </section>
</x-admin::layouts>
