<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.integrations') }}</x-slot>

    @php
        $canEdit = bouncer()->hasPermission('berkay.integrations.edit');
        $gateway = ! $merchantConfigured ? 'شناسه ثبت نشده' : (! $zarinpalActive ? 'غیرفعال' : (! $currencySupported ? 'واحد پول ناسازگار' : ($sandbox ? 'حالت آزمایشی' : 'پرداخت واقعی')));
        $sms = $smsPaused ? 'متوقف از سرور' : (! $smsConfigured ? 'کلید ثبت نشده' : (! $smsEnabled ? 'ارسال خاموش' : (! $otpConfigured ? 'الگوی ورود ثبت نشده' : 'ارسال روشن')));
    @endphp

    <section class="bk-admin bk-integrations" dir="rtl" lang="fa">
        <header class="bk-integrations-heading">
            <h1>درگاه و پیامک</h1>
            <p>پرداخت مشتری، پیامک‌های فروشگاه و همگام‌سازی کالاها با ترب را از اینجا مدیریت کنید.</p>
        </header>

        @include('berkay::admin.seller-feedback')

        <nav class="bk-integrations-overview" aria-label="وضعیت سرویس‌ها">
            <a href="#payment"><span>پرداخت اینترنتی</span><strong>زرین‌پال</strong><small>{{ $gateway }}</small></a>
            <a href="#sms"><span>ورود و اطلاع‌رسانی</span><strong>کاوه‌نگار</strong><small>{{ $sms }}</small></a>
            <a href="#torob"><span>معرفی محصولات</span><strong>ترب</strong><small>{{ $torobEnabled ? 'مسیر دریافت فعال' : 'غیرفعال' }}</small></a>
        </nav>

        <div class="bk-integrations-grid">
            <form id="payment" method="post" action="{{ route('admin.berkay.integrations.update') }}" class="bk-form bk-integration-card" autocomplete="off" data-integration-form>
                @csrf
                @method('PUT')
                <header>
                    <h2>پرداخت اینترنتی با زرین‌پال</h2>
                    <p>دریافت مبلغ سفارش بعد از انتخاب درگاه توسط مشتری.</p>
                </header>

                <fieldset @disabled(! $canEdit)>
                    <label for="zarinpal_merchant_id">شناسهٔ پذیرنده</label>
                    <input id="zarinpal_merchant_id" name="zarinpal_merchant_id" type="password" autocomplete="new-password" dir="ltr" maxlength="36" aria-describedby="merchant-help">
                    <p id="merchant-help">{{ $merchantConfigured ? 'شناسه ذخیره شده است. برای حفظ آن، این کادر را خالی بگذارید.' : 'شناسهٔ ۳۶ کاراکتری درگاه را از پیشخوان زرین‌پال بردارید.' }}</p>

                    @error('zarinpal_merchant_id')
                        <p class="bk-error">{{ $message }}</p>
                    @enderror

                    <div class="bk-form-pair">
                        <div>
                            <label for="zarinpal_active">نمایش درگاه در سبد خرید</label>
                            <select id="zarinpal_active" name="zarinpal_active">
                                <option value="0" @selected(! old('zarinpal_active', $zarinpalActive))>نمایش داده نشود</option>
                                <option value="1" @selected(old('zarinpal_active', $zarinpalActive))>در دسترس مشتری</option>
                            </select>
                        </div>
                        <div>
                            <label for="zarinpal_sandbox">نوع پرداخت</label>
                            <select id="zarinpal_sandbox" name="zarinpal_sandbox" aria-describedby="payment-mode">
                                <option value="1" @selected(old('zarinpal_sandbox', $sandbox))>آزمایشی · بدون جابه‌جایی پول</option>
                                <option value="0" @selected(! old('zarinpal_sandbox', $sandbox))>واقعی · دریافت مبلغ سفارش</option>
                            </select>
                        </div>
                    </div>
                    <p id="payment-mode" class="bk-integration-warning" data-payment-warning>اگر درگاه را در حالت واقعی فعال کنید، مشتری می‌تواند مبلغ سفارش را پرداخت کند.</p>
                </fieldset>

                <details class="bk-integration-help">
                    <summary>چک‌لیست راه‌اندازی درگاه</summary>
                    <ol>
                        <li>در پیشخوان زرین‌پال وضعیت پذیرنده و دامنهٔ ثبت‌شده را بررسی کنید.</li>
                        <li>شناسهٔ پذیرنده را وارد کنید و واحد پول فروشگاه را روی تومان یا ریال بگذارید.</li>
                        <li>یک سفارش با حالت آزمایشی ثبت کنید و بازگشت به صفحهٔ سفارش را بررسی کنید.</li>
                        <li>پس از آماده شدن پذیرنده، «پرداخت واقعی» را انتخاب و تنظیمات را ذخیره کنید.</li>
                    </ol>
                    <p>واحد پول فعلی: <bdi>{{ $currency ?? 'نامشخص' }}</bdi> · {{ $currencySupported ? 'سازگار با درگاه' : 'نیازمند تغییر به ریال یا تومان' }}</p>
                </details>
            </form>

            <section id="sms" class="bk-integration-card" aria-labelledby="integration-sms-title">
                <header>
                    <h2 id="integration-sms-title">پیامک‌های کاوه‌نگار</h2>
                    <p>کد ورود، اطلاع‌رسانی سفارش و ارسال کد تخفیف.</p>
                </header>

                <dl class="bk-integration-checklist">
                    <div><dt>کلید اتصال</dt><dd>{{ $smsConfigured ? 'ذخیره شده' : 'وارد نشده' }}</dd></div>
                    <div><dt>الگوی کد ورود</dt><dd>{{ $otpConfigured ? 'نام الگو ثبت شده' : 'وارد نشده' }}</dd></div>
                    <div><dt>ارسال پیامک</dt><dd>{{ $smsPaused ? 'متوقف از سرور' : ($smsEnabled ? 'روشن' : 'خاموش') }}</dd></div>
                </dl>
                <p class="bk-integration-muted">پس از تأیید الگوها در کاوه‌نگار، با شمارهٔ خودتان یک پیامک آزمایشی بفرستید. خاموش کردن ارسال، دریافت کد ورود را هم متوقف می‌کند.</p>
                <div class="bk-integration-actions">
                    <a class="bk-button" href="{{ route('admin.berkay.kavenegar.index') }}">تنظیم پیامک‌ها</a>
                    @if ($canEdit)
                        <a class="bk-outline-button" href="{{ route('admin.berkay.kavenegar.index') }}#sms-manual">ارسال آزمایشی و تخفیف</a>
                    @endif
                </div>
            </section>

            <section id="torob" class="bk-form bk-integration-card" aria-labelledby="integration-torob-title">
                <header>
                    <h2 id="integration-torob-title">همگام‌سازی محصولات با ترب</h2>
                    <p>ترب از این مسیر قیمت، موجودی و تصویر کالاها را دریافت می‌کند.</p>
                </header>

                <div>
                    <label for="torob-endpoint">نشانی دریافت محصولات</label>
                    <div class="bk-integration-copy">
                        <input id="torob-endpoint" type="url" readonly dir="ltr" value="{{ route('shop.berkay.torob.products') }}">
                        <button type="button" class="bk-outline-button" data-copy-endpoint hidden>کپی نشانی</button>
                    </div>
                    <p role="status" data-copy-status></p>
                </div>

                <fieldset @disabled(! $canEdit)>
                    <label for="torob_enabled">دسترسی ترب به محصولات</label>
                    <select id="torob_enabled" name="torob_enabled" form="payment">
                        <option value="0" @selected(! old('torob_enabled', $torobEnabled))>غیرفعال</option>
                        <option value="1" @selected(old('torob_enabled', $torobEnabled))>فعال</option>
                    </select>
                </fieldset>

                <details class="bk-integration-help">
                    <summary>از کجا بفهمم اتصال برقرار است؟</summary>
                    <p>ابتدا دامنهٔ دارای HTTPS را در پنل فروشندگان ترب ثبت کنید. بعد از فعال‌سازی، نتیجهٔ دریافت کالاها را همان‌جا بررسی کنید. باز کردن نشانی در مرورگر برای آزمون کافی نیست، چون درخواست ترب باید احراز هویت شود.</p>
                </details>
            </section>

            <aside class="bk-integration-card bk-integration-reference">
                <h2>پیش از شروع فروش</h2>
                <ul>
                    <li>وضعیت‌های این صفحه از تنظیمات ذخیره‌شده خوانده می‌شوند. نتیجهٔ پرداخت یا تحویل پیامک را در آزمون هر سرویس بررسی کنید.</li>
                    <li>اطلاعات احراز هویت را فقط در پیشخوان خود زرین‌پال بارگذاری کنید.</li>
                </ul>
                <a class="bk-outline-button" href="https://cdn.zarinpal.com/files/auth.pdf" target="_blank" rel="noopener noreferrer">فرم احراز هویت زرین‌پال</a>
            </aside>
        </div>

        @if ($canEdit)
            <footer class="bk-integration-save">
                <p data-integration-save-status>این دکمه تنظیمات زرین‌پال و ترب را ذخیره می‌کند.</p>
                <button type="submit" form="payment" class="bk-button">ذخیرهٔ زرین‌پال و ترب</button>
            </footer>
        @endif
    </section>

    @pushOnce('scripts')
        <script type="module" src="{{ asset('berkay/integration-settings.js') }}?v={{ filemtime(public_path('berkay/integration-settings.js')) }}"></script>
    @endPushOnce
</x-admin::layouts>
