@php
    $example = trans('berkay::sms.example-'.$event);
    $pattern = old('templates.'.$event, $templates[$event]);
    $token = match ($group) {
        'orders' => 'order-number',
        'extras' => $event === 'coupon' ? 'coupon-token' : 'account-code',
        default => 'account-code',
    };
    $secondToken = match ($event) {
        'shipped' => 'tracking-number',
        'status' => 'order-status',
        default => null,
    };
    $description = match ($event) {
        'login' => 'login-pattern',
        'register', 'change', 'delete', 'test' => 'fallback-code',
        'coupon' => 'manual-pattern',
        'order' => 'order-pattern',
        'status' => 'status-pattern',
        default => null,
    };
    $suggestedName = match ($event) {
        'login' => 'otp',
        'order' => 'new-order-user',
        'stage_review' => 'order-awaiting-review',
        'stage_approved' => 'order-approved',
        'shipped' => 'order-shipped-user',
        'status' => 'order-status-user',
        default => null,
    };
    $editable = bouncer()->hasPermission('berkay.integrations.edit');
@endphp
<article class="bk-sms-card" aria-labelledby="sms-{{ $event }}-title">
    <header class="bk-sms-card-heading">
        <span class="bk-sms-card-icon {{ $group === 'orders' ? 'icon-sales' : 'icon-settings' }}" aria-hidden="true"></span>
        <div>
            <h3 id="sms-{{ $event }}-title">{{ trans('berkay::sms.template-'.$event) }}</h3>
            @if ($description)
                <p>{{ trans('berkay::sms.'.$description) }}</p>
            @endif
        </div>
        @if ($group === 'orders')
            <label class="bk-sms-toggle">
                @if ($editable)
                    <input type="hidden" name="notifications[{{ $event }}]" value="0">
                @endif
                <input type="checkbox" name="notifications[{{ $event }}]" value="1" role="switch"
                    @checked(old('notifications.'.$event, $notifications[$event]))
                    @disabled(! $editable)
                    aria-label="{{ trans('berkay::sms.template-'.$event) }}">
                <span class="bk-sms-toggle-on" aria-hidden="true">{{ trans('berkay::sms.event-enabled') }}</span>
                <span class="bk-sms-toggle-off" aria-hidden="true">{{ trans('berkay::sms.event-disabled') }}</span>
            </label>
        @endif
    </header>
    <div class="bk-sms-card-body">
        <label for="template-{{ $event }}">{{ trans('berkay::sms.pattern-name') }}</label>
        <input id="template-{{ $event }}" name="templates[{{ $event }}]" value="{{ $pattern }}"
            maxlength="100" dir="ltr" placeholder="{{ $suggestedName }}" aria-describedby="hint-{{ $event }}" @readonly(! $editable)>
        @error('templates.'.$event)
            <small class="text-red-700 dark:text-red-400">{{ $message }}</small>
        @enderror
        <ul id="hint-{{ $event }}" class="bk-sms-tokens">
            <li><code dir="ltr">%token</code> {{ trans('berkay::sms.'.$token) }}</li>
            @if ($secondToken)
                <li><code dir="ltr">%token2</code> {{ trans('berkay::sms.'.$secondToken) }}</li>
            @endif
        </ul>
        <div class="bk-sms-example">
            <header>
                <strong>{{ trans('berkay::sms.suggested') }}</strong>
                <button type="button" data-sms-copy="{{ $example }}"
                    data-copied="{{ trans('berkay::sms.copied') }}" data-copy-failed="{{ trans('berkay::sms.copy-failed') }}">
                    {{ trans('berkay::sms.copy') }}
                </button>
            </header>
            <p>@foreach (preg_split('/(%token2?)/', $example, -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if (in_array($part, ['%token', '%token2'], true))<code dir="ltr">{{ $part }}</code>@else{{ $part }}@endif @endforeach</p>
            <small role="status" hidden></small>
        </div>
    </div>
</article>
