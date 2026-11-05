<x-shop::layouts :has-feature="false">
    <x-slot:title>{{ trans('shop::app.compare.title') }}</x-slot:title>
    <section class="bk-compare-page">
        <v-compare></v-compare>
    </section>
    @pushOnce('scripts')
        <script type="text/x-template" id="v-compare-template">
            <div>
                <div class="bk-compare-heading">
                    <h1>{{ trans('shop::app.compare.title') }}</h1>
                    <a class="bk-button" href="{{ route('shop.search.index') }}">{{ trans('berkay::app.all-products') }}</a>
                    <button v-if="items.length" type="button" @click="removeAll">{{ trans('shop::app.compare.delete-all') }}</button>
                </div>
                <p v-if="isLoading" role="status" aria-busy="true" aria-label="{{ trans('shop::app.compare.title') }}">…</p>
                <div v-else-if="items.length" class="bk-compare-scroll" tabindex="0" aria-label="{{ trans('shop::app.compare.title') }}">
                    <table class="bk-compare-table" :style="{width: (160 + items.length * 240) + 'px'}">
                        <thead><tr>
                            <th scope="col">{{ trans('shop::app.compare.title') }}</th>
                            <th v-for="product in items" :key="product.id" scope="col">
                                <button type="button" class="bk-compare-remove" @click="remove(product.id)">{{ trans('shop::app.checkout.cart.index.remove') }}</button>
                                <a :href="'{{ route('shop.product_or_category.index', ':slug') }}'.replace(':slug', product.url_key)">
                                    <img :src="product.base_image.medium_image_url" :alt="product.name" width="180" height="180">
                                    <span v-text="product.name"></span>
                                </a>
                                <p v-text="product.min_price"></p>
                            </th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="attribute in comparableAttributes.filter(a => !['product', 'name', 'price'].includes(a.code))" :key="attribute.code">
                                <th scope="row" v-text="attribute.name || attribute.admin_name"></th>
                                <td v-for="product in items" :key="product.id" v-text="plainText(product[attribute.code])"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="bk-compare-empty">{{ trans('shop::app.compare.empty-text') }}</p>
            </div>
        </script>
        <script type="module">
            app.component("v-compare", {
                template: '#v-compare-template',

                data() {
                    return  {
                        comparableAttributes: [
                            ...[{'code': 'product', 'name': @json(trans('shop::app.compare.title'))}],
                            ...@json($comparableAttributes)
                        ],

                        items: [],

                        isCustomer: '{{ auth()->guard('customer')->check() }}',

                        isLoading: true,
                    }
                },

                mounted() {
                    this.getItems();
                },

                methods: {
                    plainText(value) { if (value == null || value === '') return '—'; const doc = new DOMParser().parseFromString(String(value), 'text/html'); return doc.body.textContent || '—'; },
                    getItems() {
                        let productIds = [];

                        if (! this.isCustomer) {
                            productIds = this.getStorageValue('compare_items');
                        }

                        this.$axios.get("{{ route('shop.api.compare.index') }}", {
                                params: {
                                    product_ids: productIds,
                                },
                            })
                            .then(response => {
                                this.isLoading = false;

                                this.items = response.data.data?.data ?? response.data.data;
                            })
                            .catch(error => { this.isLoading = false; this.$emitter.emit('add-flash', { type: 'error', message: @json(trans('shop::app.compare.remove-error')) }); });
                    },

                    remove(productId) {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                if (! this.isCustomer) {
                                    const index = this.items.findIndex((item) => item.id === productId);

                                    this.items.splice(index, 1);

                                    let items = this.getStorageValue()
                                        .filter(item => item != productId);

                                    localStorage.setItem('compare_items', JSON.stringify(items));

                                    return;
                                }

                                this.$axios.post("{{ route('shop.api.compare.destroy') }}", {
                                        '_method': 'DELETE',
                                        'product_id': productId,
                                    })
                                    .then(response => {
                                        this.items = response.data.data?.data ?? response.data.data;

                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                    })
                                    .catch(error => {
                                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? @json(trans('shop::app.compare.remove-error')) });
                                    });
                            }
                        });
                    },

                    removeAll() {
                        this.$emitter.emit('open-confirm-modal', {
                            agree: () => {
                                if (! this.isCustomer) {
                                    localStorage.removeItem('compare_items');

                                    this.items = [];

                                    this.$emitter.emit('add-flash', { type: 'success', message:  "@lang('shop::app.compare.remove-all-success')" });

                                    return;
                                }

                                this.$axios.post("{{ route('shop.api.compare.destroy_all') }}", {
                                        '_method': 'DELETE',
                                    })
                                    .then(response => {
                                        this.items = [];

                                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                    })
                                    .catch(error => { this.isLoading = false; this.$emitter.emit('add-flash', { type: 'error', message: @json(trans('shop::app.compare.remove-error')) }); });
                            }
                        });
                    },

                    getStorageValue() {
                        let value = localStorage.getItem('compare_items');

                        if (! value) {
                            return [];
                        }

                        try { const ids = JSON.parse(value); return Array.isArray(ids) ? ids : []; } catch { return []; }
                    },
                }
            });
        </script>
    @endpushOnce
</x-shop::layouts>
