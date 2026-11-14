<x-shop::layouts :has-feature="false">
    <x-slot:title>جایزه‌های من</x-slot>
    <section class="bk-section bk-campaign-rewards" dir="rtl">
        <h1>جایزه‌های من</h1>
        @forelse ($results as $result)
            @php($prize = json_decode($result->prize, true))
            <article>
                <h2>{{ $prize['label'] }}</h2>
                <p>{{ \Webkul\Berkay\Services\StoreCampaign::EVENTS[$result->event] ?? $result->event }}</p>
                @if ($result->coupon)
                    <label for="reward-{{ $result->id }}">کد یک‌بار مصرف سبد خرید</label>
                    <input id="reward-{{ $result->id }}" class="bk-wheel-coupon" readonly dir="ltr" value="{{ $result->coupon }}">
                    <p>مهلت استفاده: <bdi>{{ \Illuminate\Support\Carbon::parse($result->created_at)->addDays($prize['days'])->format('Y/m/d H:i') }}</bdi></p>
                @elseif ($prize['type'] === 'product')
                    <p>{{ $result->fulfilled_at ? 'تحویل شد' : 'در انتظار هماهنگی تحویل' }}</p>
                    @if (! $result->fulfilled_at)<a href="{{ route('shop.home.contact_us') }}">تماس با فروشگاه</a>@endif
                @endif
            </article>
        @empty
            <p>هنوز نتیجه‌ای برای حساب شما ثبت نشده است.</p>
        @endforelse
        {{ $results->links() }}
        <a class="bk-button" href="{{ route('shop.customers.account.profile.index') }}">بازگشت به حساب کاربری</a>
    </section>
</x-shop::layouts>
