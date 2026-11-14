{!! view_render_event('bagisto.shop.checkout.onepage.address.customer.before') !!}

<v-checkout-address-customer :cart="cart" :current-step="currentStep"
    :busy="isPlacingOrder || stageBusy" @processing="stepForward" @processed="stepProcessed">
    <x-shop::shimmer.checkout.onepage.address />
</v-checkout-address-customer>

{!! view_render_event('bagisto.shop.checkout.onepage.address.customer.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-checkout-address-customer-template">
        <div>
            <p v-if="isLoading" class="bk-checkout-muted" role="status">در حال دریافت آدرس‌ها…</p>
            <p v-else-if="loadFailed" role="alert">
                آدرس‌های قبلی بارگیری نشد.
                <button type="button" class="bk-checkout-link" @click="loadAddresses">تلاش دوباره</button>
            </p>
            <template v-else>
                <div v-if="currentStep !== 'address' && selected.billing" class="bk-checkout-address-preview">
                    <div>
                        <strong>@{{ recipientName(deliveryAddress) }}</strong>
                        <p>@{{ addressLine(deliveryAddress) }}</p>
                    </div>
                    <button type="button" class="bk-checkout-link" :disabled="busy" @click="$emit('processing', 'address')">تغییر آدرس</button>
                </div>
                <template v-else>
                    <p v-if="feedback" class="bk-checkout-error" role="alert">@{{ feedback }}</p>
                    <template v-if="editing">
                        <div class="bk-checkout-address-bar">
                            <h3>@{{ draft.id ? 'ویرایش آدرس' : 'افزودن آدرس جدید' }}</h3>
                            <button v-if="availableAddresses(editing).length || (editing === 'shipping' && selected.billing)" type="button" class="bk-checkout-link"
                                :disabled="isStoring" @click="closeEditor">بازگشت به آدرس‌ها</button>
                        </div>
                        <x-shop::form as="div" v-slot="{ handleSubmit }">
                            <form @submit="handleSubmit($event, saveAddress)">
                                <v-checkout-address-form :key="editing" :control-name="editing" :address="draft"></v-checkout-address-form>
                                <label v-if="!cart.is_guest && draft.address_type !== 'customer'" class="bk-checkout-check">
                                    <input type="checkbox" v-model="rememberAddress"> ذخیره در دفترچهٔ آدرس برای خریدهای بعدی
                                </label>
                                <button type="submit" class="bk-checkout-primary" :disabled="isStoring">
                                    @{{ isStoring ? 'در حال ذخیره…' : 'تأیید و استفاده از این آدرس' }}
                                </button>
                            </form>
                        </x-shop::form>
                    </template>
                    <template v-else>
                        <p class="bk-checkout-muted">یکی از آدرس‌ها را انتخاب کنید یا آدرس تازه‌ای وارد کنید.</p>
                        <fieldset v-for="kind in addressKinds" :key="kind" class="bk-checkout-address-group" :disabled="isStoring">
                            <legend>@{{ kind === 'shipping' ? 'آدرس ارسال' : (cart.have_stockable_items && sameAddress ? 'آدرس تحویل سفارش' : 'آدرس صورتحساب') }}</legend>
                            <div class="bk-checkout-address-list">
                                <div v-for="address in availableAddresses(kind)" :key="addressKey(address)" class="bk-checkout-address-card"
                                    :class="{ selected: selected[kind] === address }">
                                    <label>
                                        <input type="radio" :name="'checkout-address-' + kind" :checked="selected[kind] === address"
                                            @change="selected[kind] = address; feedback = ''">
                                        <span>
                                            <strong>@{{ recipientName(address) }}</strong>
                                            <span v-if="address.default_address" class="bk-checkout-default">پیش‌فرض</span>
                                            <span v-else-if="address.address_type !== 'customer'" class="bk-checkout-default">این سفارش</span>
                                            <span class="bk-checkout-address-text">@{{ addressLine(address) }}</span>
                                            <span v-if="address.phone" class="bk-checkout-address-text"><bdi>@{{ address.phone }}</bdi></span>
                                        </span>
                                    </label>
                                    <button type="button" class="bk-checkout-link" @click="openEditor(kind, address)"
                                        :aria-label="'ویرایش آدرس ' + recipientName(address)">ویرایش</button>
                                </div>
                            </div>
                            <button type="button" class="bk-checkout-secondary" @click="openEditor(kind)">
                                <span aria-hidden="true">+</span> @{{ kind === 'shipping' ? 'افزودن آدرس ارسال' : 'افزودن آدرس جدید' }}
                            </button>
                        </fieldset>
                        <label v-if="cart.have_stockable_items" class="bk-checkout-check">
                            <input type="checkbox" v-model="sameAddress" :disabled="isStoring" @change="prepareShipping">
                            آدرس ارسال و صورتحساب یکسان است
                        </label>
                        <button type="button" class="bk-checkout-primary" :disabled="isStoring || !selected.billing || (!sameAddress && !selected.shipping && cart.have_stockable_items)"
                            @click="submitAddresses">
                            @{{ isStoring ? 'در حال بررسی…' : (cart.have_stockable_items ? 'ادامه؛ انتخاب روش ارسال' : 'ادامه؛ انتخاب روش پرداخت') }}
                        </button>
                    </template>
                </template>
            </template>
        </div>
    </script>

    <script type="module">
        app.component('v-checkout-address-customer', {
            template: '#v-checkout-address-customer-template',
            props: ['cart', 'currentStep', 'busy'],
            emits: ['processing', 'processed'],

            data() {
                return {
                    saved: [],
                    temporary: { billing: null, shipping: null },
                    selected: { billing: null, shipping: null },
                    sameAddress: this.cart.billing_address ? !!this.cart.billing_address.use_for_shipping : true,
                    editing: null,
                    draft: null,
                    rememberAddress: true,
                    isLoading: true,
                    isStoring: false,
                    loadFailed: false,
                    feedback: '',
                };
            },

            computed: {
                addressKinds() {
                    return this.cart.have_stockable_items && !this.sameAddress ? ['billing', 'shipping'] : ['billing'];
                },
                deliveryAddress() {
                    return this.addressKinds.includes('shipping') ? this.selected.shipping : this.selected.billing;
                },
            },

            mounted() {
                this.loadAddresses();
            },

            methods: {
                availableAddresses(kind) {
                    return this.temporary[kind] ? [this.temporary[kind], ...this.saved] : this.saved;
                },
                addressKey(address) {
                    return address.address_type === 'customer' ? 'saved-' + address.id : 'temporary';
                },
                recipientName(address) {
                    return [address?.first_name, address?.last_name].filter(Boolean).join(' ');
                },
                addressLine(address) {
                    return [address?.city, ...(address?.address ?? []), address?.postcode].filter(Boolean).join('، ');
                },
                async loadAddresses() {
                    this.isLoading = true;
                    this.loadFailed = false;
                    try {
                        const {data} = this.cart.is_guest ? {data: {data: []}}
                            : await this.$axios.get(@js(route('shop.api.customers.account.addresses.index')));
                        if (!Array.isArray(data.data)) throw new Error('Invalid address list');
                        this.saved = data.data;
                        for (const kind of ['billing', 'shipping']) {
                            const existing = this.cart[kind + '_address'];
                            const parent = existing?.parent_address_id;
                            const saved = this.saved.find(address => parent && Number(address.id) === Number(parent));
                            if (existing && !saved) {
                                this.temporary[kind] = {...existing, id: 0, address_type: 'checkout'};
                            }
                            this.selected[kind] = saved ?? this.temporary[kind] ?? this.saved.find(address => address.default_address) ?? this.saved[0] ?? null;
                        }
                        if (!this.selected.billing) this.openEditor('billing');
                        else this.prepareShipping();
                    } catch {
                        this.loadFailed = true;
                    } finally {
                        this.isLoading = false;
                    }
                },
                prepareShipping() {
                    if (!this.sameAddress && !this.selected.shipping && this.cart.have_stockable_items) this.openEditor('shipping');
                },
                openEditor(kind, address = null) {
                    this.feedback = '';
                    this.editing = kind;
                    this.rememberAddress = true;
                    this.draft = address ?? {
                        id: 0,
                        first_name: @js(auth('customer')->user()?->first_name ?? ''),
                        last_name: @js(auth('customer')->user()?->last_name ?? ''),
                        phone: @js(auth('customer')->user()?->phone ?? ''),
                        address: [],
                        country: 'IR',
                    };
                },
                closeEditor() {
                    this.feedback = '';
                    this.editing = null;
                    this.draft = null;
                    if (!this.selected.shipping) this.sameAddress = true;
                },
                async saveAddress(values, {setErrors}) {
                    if (this.isStoring || !this.editing) return;
                    this.isStoring = true;
                    this.feedback = '';
                    const kind = this.editing;
                    const previous = this.draft;
                    const payload = {...previous, ...values[kind], id: previous.id};
                    try {
                        if (!this.cart.is_guest && (previous.address_type === 'customer' || this.rememberAddress)) {
                            const editingSaved = previous.address_type === 'customer';
                            const response = editingSaved
                                ? await this.$axios.put(@js(route('shop.api.customers.account.addresses.update')).replace(/\/$/, '') + '/' + previous.id, payload)
                                : await this.$axios.post(@js(route('shop.api.customers.account.addresses.store')), payload);
                            const address = response.data.data;
                            if (!address?.id) throw new Error('Invalid saved address');
                            if (editingSaved) {
                                this.saved = this.saved.map(item => item.id === address.id ? address : item);
                                for (const key of ['billing', 'shipping']) {
                                    if (this.addressKey(this.selected[key] ?? {}) === this.addressKey(address)) this.selected[key] = address;
                                }
                            } else {
                                this.saved.unshift(address);
                                if (previous.address_type === 'checkout') this.temporary[kind] = null;
                                if (!this.selected.shipping) this.selected.shipping = address;
                            }
                            this.selected[kind] = address;
                        } else {
                            this.temporary[kind] = {...payload, id: 0, address_type: 'checkout'};
                            this.selected[kind] = this.temporary[kind];
                        }
                        this.editing = null;
                        this.draft = null;
                        this.prepareShipping();
                    } catch (error) {
                        const errors = error.response?.data?.errors;
                        if (errors) {
                            setErrors(Object.fromEntries(Object.entries(errors).map(([key, messages]) => [kind + '.' + key, messages])));
                        }
                        this.feedback = error.response?.data?.message || 'ذخیرهٔ آدرس ممکن نشد. دوباره تلاش کنید.';
                    } finally {
                        this.isStoring = false;
                    }
                },
                async submitAddresses() {
                    if (this.isStoring || !this.selected.billing || (this.addressKinds.includes('shipping') && !this.selected.shipping)) return;
                    this.isStoring = true;
                    this.feedback = '';
                    const payload = {billing: {...this.selected.billing, use_for_shipping: this.sameAddress}};
                    if (this.addressKinds.includes('shipping')) payload.shipping = this.selected.shipping;
                    try {
                        const {data} = await this.$axios.post(@js(route('shop.checkout.onepage.addresses.store')), payload);
                        const result = data.redirect === undefined ? data.data : data;
                        if (result.redirect) {
                            window.location.href = result.redirect_url || result.data;
                            return;
                        }
                        const methods = this.cart.have_stockable_items ? result.data?.shippingMethods : result.data?.payment_methods;
                        this.$emit('processing', this.cart.have_stockable_items ? 'shipping' : 'payment');
                        this.$emit('processed', methods ?? []);
                    } catch (error) {
                        this.feedback = Object.values(error.response?.data?.errors ?? {}).flat()[0]
                            || error.response?.data?.message || 'تأیید آدرس ممکن نشد. دوباره تلاش کنید.';
                    } finally {
                        this.isStoring = false;
                    }
                },
            },
        });
    </script>
@endPushOnce
