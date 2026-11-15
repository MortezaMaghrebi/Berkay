<x-shop::layouts :has-feature="false">
    <x-slot:title>جایزه‌های من</x-slot>

    <section class="bk-section bk-campaign-rewards" dir="rtl" aria-labelledby="bk-rewards-title">
        <header class="bk-rewards-header">
            <div>
                <a class="bk-rewards-link" href="{{ route('shop.customers.account.profile.index') }}">حساب کاربری <span aria-hidden="true">←</span></a>
                <h1 id="bk-rewards-title">جایزه‌های من</h1>
                <p>نتیجه‌های گردونه و کدهای جایزهٔ شما</p>
            </div>
            @if ($wheelAvailable && $results->isNotEmpty())
                <a class="bk-rewards-action" href="{{ route('shop.berkay.campaign.index') }}#wheel">مشاهدهٔ گردونه <span aria-hidden="true">←</span></a>
            @endif
        </header>

        @forelse ($results as $result)
            @php
                $prize = json_decode($result->prize, true) ?? [];
                $productGift = ($prize['type'] ?? null) === 'product';
                $issuedAt = \Illuminate\Support\Carbon::parse($result->created_at);
                $expiresAt = $result->coupon ? $issuedAt->copy()->addDays((int) ($prize['days'] ?? 7)) : null;
                $uses = $couponUses->get($result->coupon);
                [$status, $tone] = match (true) {
                    (int) $uses > 0 => ['استفاده شده', 'quiet'],
                    ! empty($result->fulfilled_at) => ['تحویل داده شد', 'quiet'],
                    ! empty($result->reset_at) => ['بسته شده', 'quiet'],
                    $expiresAt?->isPast() => ['منقضی شده', 'quiet'],
                    $result->coupon && $uses !== null => ['قابل استفاده', 'active'],
                    (bool) $result->coupon => ['غیرفعال', 'quiet'],
                    $productGift => ['آمادهٔ هماهنگی تحویل', 'pending'],
                    default => ['بدون جایزه', 'quiet'],
                };
            @endphp
            <article class="bk-reward">
                <header class="bk-reward-heading">
                    <div>
                        <p>{{ \Webkul\Berkay\Services\StoreCampaign::EVENTS[$result->event] ?? $result->event }}</p>
                        <h2>{{ $prize['label'] ?? 'نتیجهٔ گردونه' }}</h2>
                    </div>
                    <span class="bk-reward-status" data-tone="{{ $tone }}">{{ $status }}</span>
                </header>
                <div class="bk-reward-details">
                    <span>تاریخ ثبت <time dir="ltr" datetime="{{ $issuedAt->toAtomString() }}">{{ $issuedAt->format('Y/m/d') }}</time></span>
                    @if ($expiresAt && $tone === 'active')
                        <span>مهلت استفاده <time dir="ltr" datetime="{{ $expiresAt->toAtomString() }}">{{ $expiresAt->format('Y/m/d H:i') }}</time></span>
                        @if (! empty($prize['max_purchase']))
                            <span>سقف مبلغ مشمول تخفیف <strong><bdi dir="ltr">{{ \Webkul\Berkay\Services\StoreCampaign::formatTomanAmount($prize['max_purchase']) }}</bdi> تومان</strong></span>
                        @endif
                    @endif
                </div>

                @if ($tone === 'active')
                    <div class="bk-reward-ticket" data-reward-ticket>
                        <div>
                            <label for="reward-{{ $result->id }}">کد یک‌بار مصرف سبد خرید</label>
                            <input id="reward-{{ $result->id }}" readonly dir="ltr" spellcheck="false" value="{{ $result->coupon }}">
                        </div>
                        <button type="button" class="bk-rewards-action bk-rewards-action--primary" data-reward-copy>کپی کد</button>
                        <p class="bk-reward-copy-feedback" data-reward-copy-feedback role="status"></p>
                    </div>
                    <a class="bk-rewards-link" href="{{ route('shop.checkout.cart.index') }}">رفتن به سبد خرید <span aria-hidden="true">←</span></a>
                @elseif ($tone === 'pending')
                    <a class="bk-rewards-action" href="{{ route('shop.home.contact_us') }}">هماهنگی تحویل با فروشگاه</a>
                @endif
            </article>
        @empty
            <div class="bk-rewards-empty">
                <h2>نتیجه‌ای در این صفحه نیست</h2>
                @if ($results->onFirstPage())
                    <p>نتیجهٔ چرخش گردونه اینجا نمایش داده می‌شود.</p>
                    @if ($wheelAvailable)
                        <a class="bk-rewards-action bk-rewards-action--primary" href="{{ route('shop.berkay.campaign.index') }}#wheel">رفتن به گردونه <span aria-hidden="true">←</span></a>
                    @endif
                @endif
            </div>
        @endforelse

        @if ($results->hasPages())
            <nav class="bk-rewards-pages" aria-label="صفحه‌های جایزه‌ها">
                @if ($results->previousPageUrl())<a class="bk-rewards-action" rel="prev" href="{{ $results->previousPageUrl() }}">صفحهٔ قبل</a>@endif
                @if ($results->nextPageUrl())<a class="bk-rewards-action" rel="next" href="{{ $results->nextPageUrl() }}">صفحهٔ بعد</a>@endif
            </nav>
        @endif
    </section>
</x-shop::layouts>
