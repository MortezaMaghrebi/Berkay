<x-shop::layouts :has-feature="false">
    <x-slot:title>{{ ($event['title'] ?? null) ?: \Webkul\Berkay\Services\StoreCampaign::EVENTS[$event['event']] }}</x-slot>
    @include('berkay::shop.campaign-banner')
    @if ($event['wheel'])
        @php
            $colors = ['#52341f', '#c99862', '#74533f', '#d4b590', '#854d31', '#b18155', '#654d38', '#a46846'];
            $total = array_sum(array_column($event['prizes'], 'weight'));
            $position = 0;
            $segments = [];
            foreach ($event['prizes'] as $index => $option) {
                $next = $position + $option['weight'];
                $segments[] = $colors[$index].' '.(100 * $position / $total).'% '.(100 * $next / $total).'%';
                $position = $next;
            }
        @endphp
        <section class="bk-campaign-game" id="wheel" dir="rtl">
            <div class="bk-campaign-wheel" style="--wheel-colors: {{ implode(', ', $segments) }}" aria-hidden="true"><span></span></div>
            <div>
                <h1>گردونهٔ شانس</h1>
                @if ($result)
                    @php($prize = json_decode($result->prize, true))
                    <p role="status">نتیجهٔ شما: <strong>{{ $prize['label'] }}</strong></p>
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
                    <form method="post" action="{{ route('shop.berkay.campaign.spin') }}" data-campaign-spin>
                        @csrf
                        <input type="hidden" name="run" value="{{ $event['run'] }}">
                        <button type="submit" class="bk-button">چرخاندن گردونه</button>
                    </form>
                @else
                    <p>برای چرخاندن گردونه با شمارهٔ موبایل وارد حساب شوید.</p>
                    <a class="bk-button" href="{{ route('shop.customer.session.index') }}">ورود یا ساخت حساب</a>
                @endif
                @if ($customer)<p><a href="{{ route('shop.berkay.campaign.rewards') }}">مشاهدهٔ جایزه‌های من</a></p>@endif
                <details>
                    <summary>جایزه‌ها و شانس هر گزینه</summary>
                    <ul>
                        @foreach ($event['prizes'] as $option)
                            <li>{{ $option['label'] }} <span>{{ number_format(100 * $option['weight'] / $total, 2) }}٪</span></li>
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
