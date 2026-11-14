<x-admin::layouts>
    <x-slot:title>رویدادهای فروشگاه</x-slot>
    @php
        $canEdit = bouncer()->hasPermission('berkay.campaigns.edit');
        $events = \Webkul\Berkay\Services\StoreCampaign::EVENTS;
        $types = \Webkul\Berkay\Services\StoreCampaign::TYPES;
        $prizes = old('prizes', $settings['prizes'] ?? []);
    @endphp
    <section class="bk-admin bk-campaign-editor" dir="rtl">
        <h1>رویدادهای فروشگاه</h1>
        <p>یک مناسبت را در صفحهٔ اول نمایش دهید یا نمایش همهٔ رویدادها را خاموش کنید.</p>
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
                            <img src="{{ asset('berkay/campaigns/'.$key.'.jpg') }}" width="480" height="268" alt="" loading="lazy">
                            <span>{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
                <label for="campaign-title">عنوان دلخواه</label>
                <input name="title" id="campaign-title" maxlength="100" value="{{ old('title', $settings['title'] ?? '') }}" placeholder="نام مناسبت">
                <label for="campaign-description">متن کوتاه بنر</label>
                <textarea name="description" id="campaign-description" maxlength="240" rows="2">{{ old('description', $settings['description'] ?? '') }}</textarea>
            </fieldset>

            <fieldset class="bk-form-section" @disabled(! $canEdit)>
                <legend>گردونه و جوایز</legend>
                <input type="hidden" name="wheel" value="0">
                <label><input type="checkbox" name="wheel" value="1" @checked(old('wheel', $settings['wheel'] ?? false))> گردونه برای این رویداد فعال باشد</label>
                <p>هر حساب تأییدشده در هر نوبت رویداد یک بار بازی می‌کند. تغییر متن یا جوایز نوبت را از نو شروع نمی‌کند. ذخیرهٔ یک مناسبت دیگر یا «بدون رویداد» این نوبت را پایان می‌دهد.</p>
                <label for="campaign-days">اعتبار کد جایزه، به روز</label>
                <input id="campaign-days" name="days" type="number" min="1" max="90" value="{{ old('days', $settings['days'] ?? 7) }}" required>
                <p>شانس هر گزینه برابر وزن آن تقسیم بر مجموع وزن‌هاست. برای حذف یک گزینه، عنوانش را پاک کنید. برای هدیهٔ محصولی، نام دقیق هدیه را در عنوان وارد کنید.</p>
                <div class="bk-campaign-prizes">
                    @for ($i = 0; $i < 8; $i++)
                        <div class="bk-campaign-prize">
                            <div><label for="prize-label-{{ $i }}">عنوان جایزهٔ {{ $i + 1 }}</label><input id="prize-label-{{ $i }}" name="prizes[{{ $i }}][label]" maxlength="45" value="{{ $prizes[$i]['label'] ?? '' }}"></div>
                            <div><label for="prize-type-{{ $i }}">نوع</label><select id="prize-type-{{ $i }}" name="prizes[{{ $i }}][type]">
                                @foreach ($types as $type => $label)
                                    <option value="{{ $type }}" @selected(($prizes[$i]['type'] ?? 'none') === $type)>{{ $label }}</option>
                                @endforeach
                            </select></div>
                            <div><label for="prize-value-{{ $i }}">درصد یا تومان</label><input id="prize-value-{{ $i }}" name="prizes[{{ $i }}][value]" type="number" min="0" max="9000000" value="{{ $prizes[$i]['value'] ?? '' }}"></div>
                            <div><label for="prize-weight-{{ $i }}">وزن شانس</label><input id="prize-weight-{{ $i }}" name="prizes[{{ $i }}][weight]" type="number" min="1" max="10000" value="{{ $prizes[$i]['weight'] ?? 1 }}"></div>
                        </div>
                    @endfor
                </div>
            </fieldset>
            @if ($canEdit)<button class="bk-button" type="submit">ذخیرهٔ رویداد</button>@endif
        </form>
        <section class="bk-form-section bk-campaign-results">
            <h2>نتایج گردونه</h2>
            @if ($results->isEmpty())
                <p>هنوز کسی در گردونه شرکت نکرده است.</p>
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
</x-admin::layouts>
