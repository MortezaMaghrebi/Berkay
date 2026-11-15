<x-shop::layouts>
    <x-slot:title>{{ trans('berkay::workflow.pay') }}</x-slot>

    <div class="bk-section bk-reading">
        <form id="bank-payment" method="post" action="{{ $url }}" autocomplete="off">
            <input type="hidden" name="{{ $field }}" value="{{ $authority }}">
            <button type="submit" class="bk-button">{{ trans('berkay::workflow.pay') }}</button>
        </form>
    </div>

    @pushOnce('scripts')
        <script>document.getElementById('bank-payment').requestSubmit();</script>
    @endPushOnce
</x-shop::layouts>
