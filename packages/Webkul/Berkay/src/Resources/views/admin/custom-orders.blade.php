<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.custom-orders') }}</x-slot>
    <section class="bk-admin">
        <h1>{{ trans('berkay::app.custom-orders') }}</h1>
        @forelse ($requests as $inquiry)
            <article class="bk-inquiry">
                <div class="bk-toolbar"><h2>{{ $inquiry->name }} · {{ $inquiry->product_type }}</h2><span dir="ltr">{{ $inquiry->reference }}</span></div>
                <p><a href="tel:{{ $inquiry->phone }}" dir="ltr">{{ $inquiry->phone }}</a> · {{ $inquiry->created_at->format('Y-m-d H:i') }}</p>
                <p class="bk-preserve">{{ $inquiry->description }}</p>
                @if (bouncer()->hasPermission('berkay.custom-orders.edit'))
                    <form class="bk-form" method="post" action="{{ route('admin.berkay.custom_orders.update', $inquiry->id) }}">
                        @csrf @method('PUT')
                        <label for="status-{{ $inquiry->id }}">{{ trans('berkay::app.status') }}</label>
                        <select name="status" id="status-{{ $inquiry->id }}">@foreach (['new', 'reviewing', 'quoted', 'accepted', 'closed'] as $status)<option value="{{ $status }}" @selected($inquiry->status === $status)>{{ trans('berkay::app.'.$status) }}</option>@endforeach</select>
                        <label for="notes-{{ $inquiry->id }}">{{ trans('berkay::app.notes') }}</label>
                        <textarea name="admin_notes" id="notes-{{ $inquiry->id }}" rows="3" maxlength="6000">{{ $inquiry->admin_notes }}</textarea>
                        <button class="bk-button" type="submit">{{ trans('berkay::app.save') }}</button>
                    </form>
                @else
                    <p>{{ trans('berkay::app.'.$inquiry->status) }}</p>
                @endif
            </article>
        @empty
            <p>{{ trans('berkay::app.empty-requests') }}</p>
        @endforelse
        {{ $requests->links() }}
    </section>
</x-admin::layouts>
