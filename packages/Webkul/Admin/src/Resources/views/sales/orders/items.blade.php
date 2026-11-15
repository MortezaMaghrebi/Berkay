<div class="flex flex-wrap gap-1.5">
    @php
        $restCount = max($order->items->count() - 3, 0);
    @endphp

    @foreach ($order->items->take(3) as $item)
        <div class="relative">
            <div @class(['relative size-15 rounded-sm', 'max-md:size-8' => $linkedRow ?? false])>
                @if ($item->product?->images->count() > 0)
                    <img 
                        class="h-full w-full rounded-sm" 
                        src="{{ $item->product->base_image_url }}"
                    >

                    <span class="absolute bottom-px rounded-full bg-darkPink px-1.5 text-xs font-bold leading-normal text-white ltr:left-px rtl:right-px">
                        {{ $item->qty_ordered }}
                    </span>
                @else
                    <div class="relative size-full rounded-sm border border-dashed border-gray-300 dark:border-gray-800 dark:mix-blend-exclusion dark:invert">
                        <img src="{{ bagisto_asset('images/product-placeholders/front.svg') }}">
                        
                        <p @class(['absolute bottom-1.5 w-full text-center text-[6px] font-semibold text-gray-400', 'max-md:hidden' => $linkedRow ?? false])>
                            @lang('admin::app.sales.invoices.view.product-image') 
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if ($restCount >= 1)
        @if (! ($linkedRow ?? false))
            <a href="{{ route('admin.sales.orders.view', $order->id) }}">
        @endif
            <div class="flex max-w-16.25 items-center rounded-sm bg-gray-50 dark:bg-gray-800 md:min-h-15">
                <p class="px-1.5 py-1.5 text-center text-xs font-bold text-gray-600 dark:text-gray-300">
                    @lang('admin::app.sales.orders.index.datagrid.product-count', ['count' => $restCount])
                </p>
            </div>
        @if (! ($linkedRow ?? false))
            </a>
        @endif
    @endif
</div>
