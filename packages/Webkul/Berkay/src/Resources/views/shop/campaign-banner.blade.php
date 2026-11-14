@if ($event)
    @php($title = ($event['title'] ?? null) ?: \Webkul\Berkay\Services\StoreCampaign::EVENTS[$event['event']])
    <section class="bk-campaign-banner" data-event="{{ $event['event'] }}" dir="rtl" aria-label="{{ $title }}">
        <div class="bk-campaign-copy">
            <p>رویداد چرم برکای</p>
            <h2>{{ $title }}</h2>
            @if (! empty($event['description']))<p>{{ $event['description'] }}</p>@endif
            <a class="bk-button bk-campaign-link" href="{{ $event['wheel'] ? route('shop.berkay.campaign.index').'#wheel' : route('shop.search.index') }}">
                {{ $event['wheel'] ? 'شرکت در گردونه' : 'دیدن محصولات' }} <span aria-hidden="true">←</span>
            </a>
        </div>
        <div class="bk-campaign-visual">
            <img src="{{ asset('berkay/event-banners/'.$event['event'].'.jpg') }}" width="480" height="268" alt="" decoding="async">
        </div>
    </section>
@endif
