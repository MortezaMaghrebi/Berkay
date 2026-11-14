<!-- SEO Meta Content -->
@push('meta')
    <meta name="description" content="@lang('shop::app.checkout.onepage.index.checkout')"/>

    <meta name="keywords" content="@lang('shop::app.checkout.onepage.index.checkout')"/>
@endPush

<x-shop::layouts
    :has-header="false"
    :has-feature="false"
    :has-footer="false"
>
    <!-- Page Title -->
    <x-slot:title>
        @lang('shop::app.checkout.onepage.index.checkout')
    </x-slot>

    {!! view_render_event('bagisto.shop.checkout.onepage.header.before') !!}

    <!-- Page Header -->
    <header class="bk-checkout-header">
        <div class="bk-checkout-width">
            <a href="{{ route('shop.home.index') }}"><img src="{{ asset('berkay/brand-logo.png') }}" alt="" width="42" height="42"><strong>برکای</strong></a>
            <a href="{{ route('shop.checkout.cart.index') }}">بازگشت به سبد خرید <span aria-hidden="true">←</span></a>
        </div>
    </header>

    {!! view_render_event('bagisto.shop.checkout.onepage.header.after') !!}

    <!-- Page Content -->
    <div class="bk-checkout bk-checkout-width">

        {!! view_render_event('bagisto.shop.checkout.onepage.breadcrumbs.before') !!}

        <!-- Breadcrumbs -->
        <h1>تسویه‌حساب</h1>

        {!! view_render_event('bagisto.shop.checkout.onepage.breadcrumbs.after') !!}

        <!-- Checkout Vue Component -->
        <v-checkout>
            <!-- Shimmer Effect -->
            <x-shop::shimmer.checkout.onepage />
        </v-checkout>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-checkout-template"
        >
            <div v-if="cartFailed" class="bk-checkout-panel" role="alert">
                بارگیری سبد خرید ممکن نشد.
                <button type="button" class="bk-checkout-link" @click="getCart">تلاش دوباره</button>
            </div>
            <template v-else-if="! cart">
                <!-- Shimmer Effect -->
                <x-shop::shimmer.checkout.onepage />
            </template>

            <template v-else>
                <ol class="bk-checkout-progress" aria-label="مراحل تسویه‌حساب">
                    <li v-for="(step, index) in checkoutSteps" :key="step.id" :aria-current="step.id === currentStep ? 'step' : null" :class="{ done: index < activeStepIndex }">
                        <span aria-hidden="true">@{{ index < activeStepIndex ? '✓' : (index + 1).toLocaleString('fa-IR') }}</span> @{{ step.title }}
                    </li>
                </ol>
                <div class="bk-checkout-grid">
                    <div class="bk-checkout-steps">
                        <section class="bk-checkout-panel" aria-labelledby="bk-address-title">
                            <h2 id="bk-address-title">آدرس و گیرنده</h2>
                            <template v-if="cart.is_guest">
                                <p class="bk-checkout-muted">آدرس ذخیره‌شده دارید؟ <a class="bk-checkout-link" href="{{ route('shop.customer.session.index', ['checkout' => 1]) }}">ورود با شمارهٔ موبایل</a></p>
                            </template>
                            @include('shop::checkout.onepage.address.form')
                            @include('shop::checkout.onepage.address.customer')
                        </section>

                        <!-- Included Shipping Methods Blade File -->
                        <template v-if="cart.have_stockable_items && ['shipping', 'payment', 'review'].includes(currentStep)">
                            <section class="bk-checkout-panel" id="bk-checkout-shipping" aria-labelledby="bk-shipping-title">
                                <h2 id="bk-shipping-title">روش ارسال</h2>
                                {!! view_render_event('bagisto.shop.checkout.onepage.shipping_methods.before') !!}
                                <p v-if="shippingMethods && !shippingRates.length" role="alert">برای این آدرس روش ارسالی در دسترس نیست. آدرس را بررسی کنید.</p>
                                <div v-else class="bk-checkout-address-list" role="group" aria-label="روش‌های ارسال">
                                    <label v-for="rate in shippingRates" :key="rate.method" class="bk-checkout-method" :class="{ selected: selectedShipping === rate.method }">
                                        <input type="radio" name="shipping_method" :checked="selectedShipping === rate.method"
                                            :disabled="stageBusy || isPlacingOrder" @change="chooseShipping(rate.method)">
                                        <span><strong>@{{ rate.method_title }}</strong><small>@{{ rate.method_description }}</small></span>
                                        <bdi>@{{ shippingChargeLabels[rate.charge_mode] ?? rate.base_formatted_price }}</bdi>
                                    </label>
                                </div>
                                {!! view_render_event('bagisto.shop.checkout.onepage.shipping_methods.after') !!}
                            </section>
                        </template>

                        <!-- Included Payment Methods Blade File -->
                        <template v-if="['payment', 'review'].includes(currentStep)">
                            <section class="bk-checkout-panel" id="bk-checkout-payment" aria-labelledby="bk-payment-title">
                                <h2 id="bk-payment-title">روش پرداخت</h2>
                                {!! view_render_event('bagisto.shop.checkout.onepage.payment_methods.before') !!}
                                <p v-if="!Object.keys(paymentMethods ?? {}).length" role="alert">در حال حاضر روش پرداختی در دسترس نیست.</p>
                                <div v-else class="bk-checkout-address-list" role="group" aria-label="روش‌های پرداخت">
                                    <label v-for="payment in paymentMethods" :key="payment.method" class="bk-checkout-method" :class="{ selected: selectedPaymentMethod === payment.method }">
                                        <input type="radio" name="payment[method]" :checked="selectedPaymentMethod === payment.method"
                                            :disabled="stageBusy || isPlacingOrder" @change="choosePayment(payment)">
                                        <span><strong>@{{ payment.method_title }}</strong><small v-if="payment.description">@{{ payment.description }}</small></span>
                                        <img v-if="payment.image" :src="payment.image" width="36" height="36" alt="">
                                    </label>
                                </div>
                                {!! view_render_event('bagisto.shop.checkout.onepage.payment_methods.after') !!}
                            </section>
                        </template>
                    </div>

                    <!-- Included Checkout Summary Blade File For Desktop view -->
                    <aside class="bk-checkout-panel bk-checkout-summary" id="bk-checkout-review" aria-label="خلاصهٔ سفارش">
                        @include('shop::checkout.onepage.summary')
                        <p class="bk-checkout-note">{{ trans('berkay::workflow.checkout') }}</p>

                        <div
                            class="flex justify-end"
                            v-if="canPlaceOrder"
                        >
                            <template v-if="(selectedPaymentMethod || cart.payment_method) == 'paypal_smart_button'">
                                {!! view_render_event('bagisto.shop.checkout.onepage.summary.paypal_smart_button.before') !!}

                                <!-- Paypal Smart Button Vue Component -->
                                <v-paypal-smart-button></v-paypal-smart-button>

                                {!! view_render_event('bagisto.shop.checkout.onepage.summary.paypal_smart_button.after') !!}
                            </template>

                            <template v-else>
                                <x-shop::button
                                    type="button"
                                    class="bk-checkout-primary w-full"
                                    :title="trans('shop::app.checkout.onepage.summary.place-order')"
                                    ::disabled="isPlacingOrder"
                                    ::loading="isPlacingOrder"
                                    @click="placeOrder"
                                />
                            </template>
                        </div>
                    </aside>
                </div>
            </template>
        </script>

        <script type="module">
            app.component('v-checkout', {
                template: '#v-checkout-template',

                data() {
                    return {
                        cart: null,
                        cartFailed: false,

                        displayTax: {
                            prices: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_prices') }}",

                            subtotal: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_subtotal') }}",

                            shipping: "{{ core()->getConfigData('sales.taxes.shopping_cart.display_shipping_amount') }}",
                        },

                        isPlacingOrder: false,
                        stageBusy: false,

                        currentStep: 'address',

                        shippingMethods: null,
                        selectedShipping: null,
                        shippingChargeLabels: {
                            collect: @js(trans('berkay::delivery.collect')),
                            free: @js(trans('berkay::delivery.free')),
                        },

                        paymentMethods: null,

                        selectedPaymentMethod: null,

                        canPlaceOrder: false,
                    }
                },

                mounted() {
                    this.getCart();
                },

                computed: {
                    shippingRates() {
                        return Object.values(this.shippingMethods ?? {}).flatMap(group => group.rates ?? []);
                    },

                    checkoutSteps() {
                        return [
                            {id: 'address', title: 'آدرس'},
                            ...(this.cart?.have_stockable_items ? [{id: 'shipping', title: 'ارسال'}] : []),
                            {id: 'payment', title: 'پرداخت'},
                            {id: 'review', title: 'بازبینی'},
                        ];
                    },

                    activeStepIndex() {
                        return this.checkoutSteps.findIndex(step => step.id === this.currentStep);
                    },
                },

                methods: {
                    getCart() {
                        this.cartFailed = false;
                        return this.$axios.get("{{ route('shop.checkout.onepage.summary') }}")
                            .then(response => {
                                this.cart = response.data.data;
                                if (!this.cart?.items_count) window.location.href = @js(route('shop.checkout.cart.index'));
                            })
                            .catch(() => { this.cartFailed = !this.cart; });
                    },

                    stepForward(step) {
                        this.currentStep = step;

                        this.canPlaceOrder = false;
                        if (step === 'address') {
                            this.shippingMethods = null;
                            this.selectedShipping = null;
                            this.paymentMethods = null;
                            this.selectedPaymentMethod = null;
                        }
                    },

                    stepProcessed(data) {
                        if (this.currentStep == 'shipping') {
                            this.shippingMethods = data;
                        } else if (this.currentStep == 'payment') {
                            this.paymentMethods = data;
                        } else if (this.currentStep === 'review') {
                            this.canPlaceOrder = true;
                        }

                        this.getCart().finally(() => this.$nextTick(this.scrollToCurrentStep));
                    },

                    scrollToCurrentStep() {
                        const container = document.getElementById('bk-checkout-' + this.currentStep);

                        if (! container) {
                            return;
                        }

                        container.scrollIntoView({
                            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                            block: 'start'
                        });
                    },

                    async chooseShipping(method) {
                        if (this.stageBusy || this.isPlacingOrder) return;
                        this.stageBusy = true;
                        this.stepForward('shipping');
                        this.selectedShipping = method;
                        this.selectedPaymentMethod = null;
                        this.paymentMethods = null;
                        try {
                            const {data} = await this.$axios.post(@js(route('shop.checkout.onepage.shipping_methods.store')), {shipping_method: method});
                            if (data.redirect_url) { window.location.href = data.redirect_url; return; }
                            this.stepForward('payment');
                            this.stepProcessed(data.payment_methods ?? []);
                        } catch (error) {
                            this.selectedShipping = null;
                            this.handleStageError(error, 'انتخاب روش ارسال ثبت نشد. دوباره تلاش کنید.');
                        } finally {
                            this.stageBusy = false;
                        }
                    },

                    async choosePayment(payment) {
                        if (this.stageBusy || this.isPlacingOrder) return;
                        this.stageBusy = true;
                        this.stepForward('payment');
                        this.selectedPaymentMethod = payment.method;
                        try {
                            const {data} = await this.$axios.post(@js(route('shop.checkout.onepage.payment_methods.store')), {payment});
                            if (data.redirect_url) { window.location.href = data.redirect_url; return; }
                            this.stepForward('review');
                            this.stepProcessed();
                        } catch (error) {
                            this.selectedPaymentMethod = null;
                            this.handleStageError(error, 'انتخاب روش پرداخت ثبت نشد. دوباره تلاش کنید.');
                        } finally {
                            this.stageBusy = false;
                        }
                    },

                    handleStageError(error, fallback) {
                        const data = error.response?.data;
                        if (data?.redirect_url) window.location.href = data.redirect_url;
                        else this.$emitter.emit('add-flash', {type: 'error', message: data?.message || fallback});
                    },

                    placeOrder() {
                        if (!this.canPlaceOrder || this.isPlacingOrder || (this.selectedPaymentMethod || this.cart.payment_method) == 'paypal_smart_button') {
                            return;
                        }

                        this.isPlacingOrder = true;

                        this.$axios.post('{{ route('shop.checkout.onepage.orders.store') }}')
                            .then(response => {
                                const result = response.data.data ?? response.data;
                                if (result.redirect_url) {
                                    window.location.href = result.redirect_url;
                                } else if (result.message) {
                                    this.$emitter.emit('add-flash', { type: 'error', message: result.message });
                                    this.getCart();
                                } else {
                                    window.location.href = '{{ route('shop.checkout.onepage.success') }}';
                                }

                                this.isPlacingOrder = false;
                            })
                            .catch(error => {
                                this.isPlacingOrder = false

                                this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'ثبت سفارش ممکن نشد. دوباره تلاش کنید.' });
                            });
                    }
                },
            });
        </script>
    @endPushOnce
</x-shop::layouts>
