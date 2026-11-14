<x-shop::layouts :has-feature="false">
    <x-slot:title>{{ ($event['title'] ?? null) ?: \Webkul\Berkay\Services\StoreCampaign::EVENTS[$event['event']] }}</x-slot>
    @include('berkay::shop.campaign-banner')
    @if ($event['wheel'])
        @php
            $colors = ['#723b30', '#aa6836', '#466254', '#bc894e', '#53435d', '#965b60', '#345966', '#75513a'];
            $count = count($event['prizes']);
            $version = app(\Webkul\Berkay\Services\StoreCampaign::class)->wheelVersion($event);
            $prize = $result ? json_decode($result->prize, true) : null;
            $winningIndex = ($prize['wheel_version'] ?? null) === $version ? $prize['segment'] : null;
            $segments = [];
            foreach ($event['prizes'] as $index => $option) {
                $segments[] = $colors[$index].' '.(360 * $index / $count).'deg '.(360 * ($index + 1) / $count).'deg';
            }
            $rotation = $winningIndex === null ? 0 : -360 * ($winningIndex + .5) / $count;
        @endphp
        <section class="bk-campaign-game" id="wheel" dir="rtl">
            <div class="bk-campaign-wheel" aria-hidden="true">
                <div class="bk-wheel-disc" style="--wheel-colors: {{ implode(', ', $segments) }}; --wheel-rotation: {{ $rotation }}deg">
                    @foreach ($event['prizes'] as $index => $option)
                        <span class="bk-wheel-mark" style="--mark-angle: {{ 360 * ($index + .5) / $count }}deg">{{ $index + 1 }}</span>
                    @endforeach
                </div>
                <div class="bk-wheel-hub"><img src="{{ asset('berkay/brand-logo.png') }}" alt="" width="64" height="64"></div>
            </div>
            <div>
                <h1>گردونهٔ شانس</h1>
                @if ($result)
                    <p class="bk-wheel-result" role="status">نتیجهٔ شما: <strong>{{ $prize['label'] }}</strong></p>
                    @if ($result->coupon)
                        <p>این کد یک‌بار مصرف را در سبد خرید وارد کنید. مهلت استفاده تا {{ \Illuminate\Support\Carbon::parse($result->created_at)->addDays($prize['days'])->format('Y/m/d H:i') }} است.</p>
                        <label for="wheel-coupon">کد جایزه</label>
                        <input class="bk-wheel-coupon" id="wheel-coupon" dir="ltr" value="{{ $result->coupon }}" readonly>
                        <a class="bk-button" href="{{ route('shop.checkout.cart.index') }}">رفتن به سبد خرید</a>
                    @elseif ($prize['type'] === 'product')
                        <p>{{ $result->fulfilled_at ? 'تحویل هدیه ثبت شده است.' : 'هدیهٔ شما ثبت شده است. برای هماهنگی تحویل با فروشگاه تماس بگیرید.' }}</p>
                        <a href="{{ route('shop.home.contact_us') }}">تماس با فروشگاه</a>
                    @endif
                @elseif ($customer)
                    <p>برای این نوبت رویداد یک فرصت دارید.</p>
                    <form method="post" action="{{ route('shop.berkay.campaign.spin') }}" data-campaign-spin data-segments="{{ $count }}">
                        @csrf
                        <input type="hidden" name="run" value="{{ $event['run'] }}">
                        <input type="hidden" name="wheel_version" value="{{ $version }}">
                        <button type="submit" class="bk-button">چرخاندن گردونه</button>
                        <p data-wheel-status role="status"></p>
                    </form>
                @else
                    <p>برای چرخاندن گردونه با شمارهٔ موبایل وارد حساب شوید.</p>
                    <a class="bk-button" href="{{ route('shop.customer.session.index') }}">ورود یا ساخت حساب</a>
                @endif
                @if ($customer)<p><a href="{{ route('shop.berkay.campaign.rewards') }}">مشاهدهٔ جایزه‌های من</a></p>@endif
                <details>
                    <summary>گزینه‌های گردونه</summary>
                    <ul>
                        @foreach ($event['prizes'] as $index => $option)
                            <li><span class="bk-wheel-key" style="--key-color: {{ $colors[$index] }}">{{ $index + 1 }}</span> {{ $option['label'] }}</li>
                        @endforeach
                    </ul>
                </details>
            </div>
        </section>
    @endif
    @pushOnce('scripts')
        <script type="module" src="{{ asset('berkay/campaign.js') }}?v={{ filemtime(public_path('berkay/campaign.js')) }}"></script>
    @endPushOnce
</x-shop::layouts>
