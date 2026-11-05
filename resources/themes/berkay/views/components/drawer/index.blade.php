@props([
    'isActive' => false,
    'position' => 'right',
    'width'    => '500px',
])

<v-drawer
    {{ $attributes }}
    is-active="{{ $isActive }}"
    position="{{ $position }}"
    width="{{ $width }}"
>
    @isset($toggle)
        <template v-slot:toggle>
            {{ $toggle }}
        </template>
    @endisset

    @isset($header)
        <template v-slot:header="{ close }">
            <div {{ $header->attributes->merge(['class' => 'grid gap-y-2.5 p-6 pb-5 max-md:gap-y-1.5 max-md:border-b max-md:border-zinc-200 max-md:p-4 max-md:gap-y-1 max-md:font-semibold']) }}>
                {{ $header }}

                <div class="absolute top-5 max-sm:top-4 ltr:right-5 rtl:left-5">
                    <button
                        type="button"
                        class="icon-cancel cursor-pointer text-3xl max-md:text-2xl focus-visible:ring-2 focus-visible:ring-navyBlue focus-visible:ring-offset-2 focus-visible:outline-hidden rounded bg-transparent border-0"
                        aria-label="{{ trans('berkay::app.storefront.close-panel') }}"
                        @click="close"
                    >
                    </button>
                </div>
            </div>
        </template>
    @endisset

    @isset($content)
        <template v-slot:content>
            <div {{ $content->attributes->merge(['class' => 'flex-1 overflow-auto px-6 max-md:px-4']) }}>
                {{ $content }}
            </div>
        </template>
    @endisset

    @isset($footer)
        <template v-slot:footer>
            <div {{ $footer->attributes->merge(['class' => 'pb-8 max-md:pb-2']) }}>
                {{ $footer }}
            </div>
        </template>
    @endisset
</v-drawer>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-drawer-template"
    >
        <div>
            <!-- Toggler -->
            <div @click="open">
                <slot name="toggle"></slot>
            </div>

            <!-- Overlay -->
            <transition
                tag="div"
                name="drawer-overlay"
                enter-active-class="bk-drawer-overlay-enter"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="bk-drawer-overlay-leave"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    class="fixed inset-0 z-20 bg-gray-500/50"
                    :class="{'bk-drawer-instant': instantMotion}"
                    aria-hidden="true"
                    @click="close"
                    v-show="isOpen"
                ></div>
            </transition>

            <!-- Content -->
            <transition
                tag="div"
                name="drawer"
                :enter-from-class="enterFromLeaveToClasses"
                enter-active-class="bk-drawer-enter"
                enter-to-class="translate-x-0"
                leave-from-class="translate-x-0"
                leave-active-class="bk-drawer-leave"
                :leave-to-class="enterFromLeaveToClasses"
            >
                <div
                    class="bk-drawer-panel fixed z-1000 overflow-hidden bg-white max-md:w-full!"
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ trans('berkay::app.storefront.panel') }}"
                    tabindex="-1"
                    :class="{
                        'bk-drawer-instant': instantMotion,
                        'inset-x-0 top-0': position == 'top',
                        'inset-x-0 bottom-0 max-sm:max-h-full': position == 'bottom',
                        'inset-y-0 ltr:right-0 rtl:left-0': position == 'right',
                        'inset-y-0 ltr:left-0 rtl:right-0': position == 'left'
                    }"
                    :style="'width:' + width"
                    v-show="isOpen"
                >
                    <div class="pointer-events-auto h-full w-full overflow-auto bg-white">
                        <div class="flex h-full w-full flex-col">
                            <div class="min-h-0 min-w-0 flex-1 overflow-auto">
                                <div class="flex h-full flex-col">
                                    <slot
                                        name="header"
                                        :close="close"
                                    >
                                        Default Header
                                    </slot>

                                    <!-- Content Slot -->
                                    <slot name="content"></slot>

                                    <!-- Footer Slot -->
                                    <slot name="footer"></slot>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </script>

    <script type="module">
        app.component('v-drawer', {
            template: '#v-drawer-template',

            props: [
                'isActive',
                'position',
                'width'
            ],

            data() {
                return {
                    isOpen: this.isActive,
                    previousFocus: null,
                    savedOverflow: '',
                    savedPadding: '',
                    instantMotion: true,
                };
            },

            mounted() {
                document.addEventListener('keydown', this.onDialogKeydown);
            },

            beforeUnmount() {
                document.removeEventListener('keydown', this.onDialogKeydown);
                if (this.isOpen) this.restoreScroll();
            },

            watch: {
                isActive(value) { value ? this.open() : this.close(); },
                isOpen(value) {
                    this.instantMotion = document.documentElement.dataset.bkInput !== 'pointer';
                    if (value) {
                        this.previousFocus = document.activeElement;
                        this.savedOverflow = document.body.style.overflow;
                        this.savedPadding = document.body.style.paddingRight;
                        const scrollbar = window.innerWidth - document.documentElement.clientWidth;
                        document.body.style.overflow = 'hidden';
                        document.body.style.paddingRight = scrollbar + 'px';
                        this.$nextTick(() => this.$refs.panel?.focus({preventScroll:true}));
                    } else {
                        this.restoreScroll();
                        this.$nextTick(() => {
                            if (this.previousFocus?.isConnected) this.previousFocus.focus({preventScroll:true});
                        });
                    }
                },
            },

            computed: {
                enterFromLeaveToClasses() {
                    if (this.position == 'top') {
                        return '-translate-y-full';
                    } else if (this.position == 'bottom') {
                        return 'translate-y-full';
                    } else if (this.position == 'left') {
                        return 'ltr:-translate-x-full rtl:translate-x-full';
                    } else if (this.position == 'right') {
                        return 'ltr:translate-x-full rtl:-translate-x-full';
                    }
                }
            },

            methods: {
                toggle() {
                    this.isOpen = !this.isOpen;
                    this.$emit('toggle', {isActive:this.isOpen});
                },
                open() {
                    this.isOpen = true;
                    this.$emit('open', {isActive:true});
                },
                close() {
                    this.isOpen = false;
                    this.$emit('close', {isActive:false});
                },
                restoreScroll() {
                    document.body.style.overflow = this.savedOverflow;
                    document.body.style.paddingRight = this.savedPadding;
                },
                onDialogKeydown(event) {
                    if (!this.isOpen || !this.$refs.panel?.contains(document.activeElement)) return;
                    if (event.key === 'Escape') {
                        event.preventDefault(); event.stopPropagation(); this.close(); return;
                    }
                    if (event.key !== 'Tab') return;
                    const controls = [...this.$refs.panel.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')]
                        .filter(element => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length);
                    const first = controls[0];
                    const last = controls[controls.length - 1];
                    if (!first) { event.preventDefault(); return; }
                    if (event.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.panel)) {
                        event.preventDefault(); last.focus();
                    } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === this.$refs.panel)) {
                        event.preventDefault(); first.focus();
                    }
                },
            },
        });
    </script>
@endPushOnce
