<x-admin::layouts>
    <x-slot:title>رویدادهای فروشگاه</x-slot>
    @php
        $canEdit = bouncer()->hasPermission('berkay.campaigns.edit');
        $events = \Webkul\Berkay\Services\StoreCampaign::EVENTS;
        $types = \Webkul\Berkay\Services\StoreCampaign::TYPES;
        $prizes = array_values(array_filter((array) old('prizes', $settings['prizes'] ?? []), 'is_array'));
    @endphp
    <section class="bk-admin bk-campaign-editor" dir="rtl">
        <header class="bk-campaign-heading">
            <div><h1>رویدادهای فروشگاه</h1><p>بنر صفحهٔ اول، گردونه و جایزه‌های مشتریان</p></div>
            <a class="bk-outline-button" href="{{ route('shop.home.index') }}" target="_blank" rel="noopener">دیدن صفحهٔ اول</a>
        </header>
        @include('berkay::admin.seller-feedback')
        <form class="bk-form" id="campaign-settings" method="post" action="{{ route('admin.berkay.campaigns.update') }}">
            @csrf
            @method('PUT')
            <fieldset class="bk-form-section" @disabled(! $canEdit)>
                <legend>بنر صفحهٔ اول</legend>
                <div class="bk-campaign-picker">
                    <label><input type="radio" name="event" value="" @checked(! old('event', $settings['event'] ?? null))> بدون رویداد</label>
                    @foreach ($events as $key => $name)
                        <label>
                            <input type="radio" name="event" value="{{ $key }}" @checked(old('event', $settings['event'] ?? null) === $key)>
                            <img src="{{ asset('berkay/event-banners/'.$key.'.jpg') }}" width="480" height="268" alt="" loading="lazy">
                            <span>{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
                <label for="campaign-title">عنوان دلخواه</label>
                <input name="title" id="campaign-title" maxlength="100" value="{{ old('title', $settings['title'] ?? '') }}" placeholder="نام مناسبت">
                <label for="campaign-description">متن کوتاه بنر</label>
                <textarea name="description" id="campaign-description" maxlength="240" rows="2">{{ old('description', $settings['description'] ?? '') }}</textarea>
                <div class="bk-campaign-preview" data-event-preview hidden>
                    <img width="480" height="268" alt="" data-preview-image>
                    <div>
                        <p>پیش‌نمایش بنر</p>
                        <strong data-preview-title></strong>
                        <p data-preview-description></p>
                        <small data-preview-action></small>
                    </div>
                </div>
            </fieldset>

            <fieldset class="bk-form-section" @disabled(! $canEdit)>
                <legend>گردونه و جوایز</legend>
                <input type="hidden" name="wheel" value="0">
                <label><input type="checkbox" name="wheel" value="1" @checked(old('wheel', $settings['wheel'] ?? false))> گردونه برای این رویداد فعال باشد</label>
                <p>هر حساب تأییدشده در هر نوبت رویداد یک بار بازی می‌کند. تغییر متن یا جوایز نوبت را از نو شروع نمی‌کند. ذخیرهٔ یک مناسبت دیگر یا «بدون رویداد» این نوبت را پایان می‌دهد.</p>
                <label for="campaign-days">اعتبار کد جایزه، به روز</label>
                <input id="campaign-days" name="days" type="number" min="1" max="90" value="{{ old('days', $settings['days'] ?? 7) }}" required>
                <p>وزن بیشتر یعنی احتمال بیشتر. برای هدیهٔ محصولی، نام دقیق محصول را در عنوان بنویسید.</p>
                <div class="bk-prize-toolbar">
                    <span data-prize-count role="status"></span>
                    @if ($canEdit)<button class="bk-outline-button" type="button" data-prize-add hidden>افزودن گزینه</button>@endif
                </div>
                <p data-prize-empty @if ($prizes) hidden @endif>هنوز گزینه‌ای ندارید. برای فعال کردن گردونه حداقل دو گزینه اضافه کنید.</p>
                <div class="bk-campaign-prizes" data-prize-list>
                    @foreach ($prizes as $i => $prize)
                        @include('berkay::admin.campaign-prize')
                    @endforeach
                </div>
            </fieldset>
            @if ($canEdit)
                <div class="bk-campaign-save">
                    <span data-save-state aria-live="polite">{{ isset($events[$settings['event'] ?? '']) ? 'رویداد منتشرشده: '.$events[$settings['event']] : 'رویدادی روی صفحهٔ اول فعال نیست' }}</span>
                    <button class="bk-button" type="submit" data-campaign-save>ذخیره و نمایش در فروشگاه</button>
                </div>
            @endif
        </form>
        <section class="bk-form-section bk-campaign-results" id="campaign-results">
            <div class="bk-prize-toolbar">
                <h2>نتایج گردونه</h2>
                <form class="bk-campaign-filter" method="get" action="{{ route('admin.berkay.campaigns.index') }}#campaign-results">
                    <label for="event-filter">مناسبت</label>
                    <select id="event-filter" name="event_filter">
                        <option value="">همهٔ مناسبت‌ها</option>
                        @foreach ($events as $key => $name)
                            <option value="{{ $key }}" @selected($filter === $key)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <button class="bk-outline-button">اعمال فیلتر</button>
                </form>
            </div>
            @if ($results->isEmpty())
                <p>نتیجه‌ای برای این فهرست ثبت نشده است.</p>
            @else
                <div class="bk-table-wrap">
                    <table class="bk-table">
                        <thead><tr><th>مشتری</th><th>رویداد</th><th>جایزه</th><th>زمان</th><th>وضعیت</th></tr></thead>
                        <tbody>
                            @foreach ($results as $result)
                                @php($prize = json_decode($result->prize, true))
                                <tr>
                                    <td>{{ trim($result->first_name.' '.$result->last_name) ?: '#'.$result->customer_id }}</td>
                                    <td>{{ $events[$result->event] ?? $result->event }}</td>
                                    <td>{{ $prize['label'] }}</td>
                                    <td><bdi>{{ $result->created_at }}</bdi></td>
                                    <td>
                                        @if ($result->fulfilled_at)
                                            تحویل شد
                                        @elseif ($prize['type'] === 'product' && $canEdit)
                                            <form method="post" action="{{ route('admin.berkay.campaigns.fulfill', $result->id) }}">
                                                @csrf
                                                <button class="bk-outline-button">ثبت تحویل هدیه</button>
                                            </form>
                                        @elseif ($prize['type'] === 'product')
                                            منتظر تحویل
                                        @elseif ($result->coupon)
                                            کد خرید صادر شد
                                        @else
                                            بدون جایزه
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $results->links() }}
            @endif
        </section>
    </section>
    @pushOnce('scripts')
        @if ($canEdit)
            <template id="campaign-prize-template">
                @include('berkay::admin.campaign-prize', ['i' => 0, 'prize' => []])
            </template>
        @endif
        <script type="module" src="{{ asset('berkay/campaign-editor.js') }}?v={{ filemtime(public_path('berkay/campaign-editor.js')) }}"></script>
    @endPushOnce
</x-admin::layouts>
