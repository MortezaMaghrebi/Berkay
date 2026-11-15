<form method="post" id="payment-review-limit" action="{{ route('admin.configuration.store', ['berkay', 'workshop']) }}" class="bk-form bk-integration-card">
    @csrf
    <input type="hidden" name="channel" value="{{ core()->getCurrentChannelCode() }}">
    <header>
        <h2>سقف پرداخت بدون تأیید مدیر</h2>
        <p>{{ trans('berkay::workflow.settingsHelp') }}</p>
    </header>
    <label for="payment-review-amount">{{ trans('berkay::workflow.amount') }}</label>
    <input id="payment-review-amount" type="number" name="berkay[workshop][review][amount_limit]" dir="ltr" min="1" max="99999999999" step="1" required
        value="{{ old('berkay.workshop.review.amount_limit', core()->getConfigData('berkay.workshop.review.amount_limit', core()->getCurrentChannelCode()) ?: \Webkul\Berkay\Services\OrderWorkflow::DEFAULT_AMOUNT_LIMIT) }}">
    @error('berkay.workshop.review.amount_limit')
        <p class="bk-error" role="alert">{{ $message }}</p>
    @enderror
    <button type="submit" class="bk-button">ذخیرهٔ سقف پرداخت</button>
</form>
