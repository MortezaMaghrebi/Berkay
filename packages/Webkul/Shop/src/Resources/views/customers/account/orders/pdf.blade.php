@php
    $direction = core()->getCurrentLocale()->direction;
@endphp

<!DOCTYPE html>
<html
    lang="{{ app()->getLocale() }}"
    dir="{{ $direction }}"
>
    <head>
        <meta
            http-equiv="Cache-control"
            content="no-cache"
        >

        <meta
            http-equiv="Content-Type"
            content="text/html; charset=utf-8"
        />

        @php
            $start = $direction === 'rtl' ? 'right' : 'left';
            $end = $direction === 'rtl' ? 'left' : 'right';
            $order = $invoice->order;
            $brand = $direction === 'rtl' ? core()->getConfigData('berkay.store.identity.name') : null;
            $brand = $brand ?: trans('berkay::app.brand');
            $logo = core()->getConfigData('sales.invoice_settings.pdf_print_outs.logo');
            $brandLogo = public_path('berkay/brand-logo.jpg');
            $logoContent = null;
            $logoType = null;

            if ($logo && Storage::exists($logo)) {
                $logoContent = Storage::get($logo);
                $logoType = Storage::mimeType($logo);
            } elseif (is_file($brandLogo)) {
                $logoContent = file_get_contents($brandLogo);
                $logoType = 'image/jpeg';
            }

            $logoSize = $logoContent ? @getimagesizefromstring($logoContent) : false;
            $logoDimension = $logoSize && $logoSize[0] < $logoSize[1] * 1.25 ? 'height: 16mm' : 'width: 20mm';
            $fontPath = [];

            if (
                app()->getLocale() === 'en'
                && $orderCurrencyCode === 'INR'
            ) {
                $fontFamily = [
                    'regular' => 'DejaVu Sans',
                    'bold'    => 'DejaVu Sans',
                ];
            } else {
                $fontFamily = [
                    'regular' => 'Arial, sans-serif',
                    'bold'    => 'Arial, sans-serif',
                ];
            }

            if (in_array(app()->getLocale(), ['ar', 'he', 'fa', 'tr', 'ru', 'uk'])) {
                $fontFamily = [
                    'regular' => 'DejaVu Sans',
                    'bold'    => 'DejaVu Sans',
                ];
            } elseif (app()->getLocale() == 'zh_CN') {
                $fontPath = [
                    'regular' => asset('fonts/NotoSansSC-Regular.ttf'),
                    'bold'    => asset('fonts/NotoSansSC-Bold.ttf'),
                ];

                $fontFamily = [
                    'regular' => 'Noto Sans SC',
                    'bold'    => 'Noto Sans SC Bold',
                ];
            } elseif (app()->getLocale() == 'ja') {
                $fontPath = [
                    'regular' => asset('fonts/NotoSansJP-Regular.ttf'),
                    'bold'    => asset('fonts/NotoSansJP-Bold.ttf'),
                ];

                $fontFamily = [
                    'regular' => 'Noto Sans JP',
                    'bold'    => 'Noto Sans JP Bold',
                ];
            } elseif (app()->getLocale() == 'hi_IN') {
                $fontPath = [
                    'regular' => asset('fonts/Hind-Regular.ttf'),
                    'bold'    => asset('fonts/Hind-Bold.ttf'),
                ];

                $fontFamily = [
                    'regular' => 'Hind',
                    'bold'    => 'Hind Bold',
                ];
            } elseif (app()->getLocale() == 'bn') {
                $fontPath = [
                    'regular' => asset('fonts/NotoSansBengali-Regular.ttf'),
                    'bold'    => asset('fonts/NotoSansBengali-Bold.ttf'),
                ];

                $fontFamily = [
                    'regular' => 'Noto Sans Bengali',
                    'bold'    => 'Noto Sans Bengali Bold',
                ];
            } elseif (app()->getLocale() == 'sin') {
                $fontPath = [
                    'regular' => asset('fonts/NotoSansSinhala-Regular.ttf'),
                    'bold'    => asset('fonts/NotoSansSinhala-Bold.ttf'),
                ];

                $fontFamily = [
                    'regular' => 'Noto Sans Sinhala',
                    'bold'    => 'Noto Sans Sinhala Bold',
                ];
            }
        @endphp

        <!-- lang supports inclusion -->
        <style type="text/css">
            @if (! empty($fontPath['regular']))
                @font-face {
                    src: url({{ $fontPath['regular'] }}) format('truetype');
                    font-family: {{ $fontFamily['regular'] }};
                }
            @endif

            @if (! empty($fontPath['bold']))
                @font-face {
                    src: url({{ $fontPath['bold'] }}) format('truetype');
                    font-family: {{ $fontFamily['regular'] }};
                    font-weight: bold;
                }
            @endif

            @page { margin: 13mm 14mm 15mm; }

            body {
                margin: 0;
                color: #2c2420;
                font-family: {{ $fontFamily['regular'] }};
                font-size: 10pt;
                line-height: 1.5;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin: 0 0 4mm;
            }

            th, td { vertical-align: top; text-align: {{ $start }}; }
            strong, th { font-weight: bold; }
            .small-text { margin: 1mm 0 0; font-size: 8.5pt; color: #594d46; }
            .amount { text-align: right; white-space: nowrap; }
            .quantity { text-align: center; }

            .masthead { border-top: 2pt solid #69432d; border-bottom: .6pt solid #d2c3b6; }
            .masthead td { padding: 4mm 0; vertical-align: middle; }
            .masthead .logo { width: 24mm; text-align: {{ $end }}; }
            .masthead h1 { margin: 0; color: #543420; font-size: 26pt; font-weight: bold; }
            .masthead .brand { margin: 0; font-size: 13pt; font-weight: bold; }

            .references { background-color: #f7f2ec; }
            .references td { padding: 3mm 4mm; }
            .reference-label { color: #594d46; font-size: 8.5pt; }
            .reference-value { font-size: 11pt; font-weight: bold; }

            .details th { padding: 1.5mm 3mm; background: #f7f2ec; color: #543420; }
            .details td { padding: 2mm 3mm; border-bottom: .6pt solid #ded4ca; }
            .origin td { padding: 0 3mm 2mm; }

            .items th { padding: 2.6mm 2.5mm; background: #69432d; color: #fff; font-size: 9pt; }
            .items td { padding: 2.8mm 2.5mm; border-bottom: .6pt solid #ded4ca; }
            .items a { color: #543420; text-decoration: underline; }
            .items td.sku { color: #594d46; font-size: 8.5pt; overflow-wrap: anywhere; }

            .summary { width: 53%; margin-{{ $start }}: auto; margin-{{ $end }}: 0; page-break-inside: avoid; }
            .summary td { padding: 1.5mm 3mm; border-bottom: .6pt solid #ded4ca; }
            .summary .grand-total td { background: #eee1d5; color: #543420; font-size: 12pt; padding: 2.8mm 3mm; }
        </style>
    </head>

    <body dir="{{ $direction }}">
        <table class="masthead">
            <tr>
                <td>
                    <h1>@lang('shop::app.customers.account.orders.invoice-pdf.invoice')</h1>
                    <p class="brand">{{ $brand }}</p>
                </td>
                @if ($logoContent)
                    <td class="logo">
                        <img src="data:{{ $logoType }};base64,{{ base64_encode($logoContent) }}" style="{{ $logoDimension }};" alt="">
                    </td>
                @endif
            </tr>
        </table>

        <table class="references">
            <tr>
                @if (core()->getConfigData('sales.invoice_settings.pdf_print_outs.invoice_id'))
                    <td>
                        <div class="reference-label">@lang('shop::app.customers.account.orders.invoice-pdf.invoice-id')</div>
                        <div class="reference-value" dir="ltr" style="text-align: {{ $start }};">#{{ $invoice->increment_id ?? $invoice->id }}</div>
                    </td>
                @endif
                @if (core()->getConfigData('sales.invoice_settings.pdf_print_outs.order_id'))
                    <td>
                        <div class="reference-label">@lang('shop::app.customers.account.orders.invoice-pdf.order-id')</div>
                        <div class="reference-value" dir="ltr" style="text-align: {{ $start }};">#{{ $order->increment_id }}</div>
                    </td>
                @endif
                <td>
                    <div class="reference-label">@lang('shop::app.customers.account.orders.invoice-pdf.date')</div>
                    <div class="reference-value" dir="ltr" style="text-align: {{ $start }};">{{ core()->formatDate($invoice->created_at, 'd-m-Y') }}</div>
                </td>
                <td>
                    <div class="reference-label">@lang('shop::app.customers.account.orders.invoice-pdf.order-date')</div>
                    <div class="reference-value" dir="ltr" style="text-align: {{ $start }};">{{ core()->formatDate($order->created_at, 'd-m-Y') }}</div>
                </td>
            </tr>
        </table>

        @php
            $originCountry = core()->getConfigData('sales.shipping.origin.country');
            $bankDetails = core()->getConfigData('sales.shipping.origin.bank_details');
        @endphp

        @if ($originCountry || $invoice->hasPaymentTerm() || $bankDetails)
            <table class="origin">
                <tr>
                    @if ($originCountry)
                        <td style="width: 50%;">
                            <strong>{{ core()->getConfigData('sales.shipping.origin.store_name') ?: $brand }}</strong>
                            <div>{{ core()->getConfigData('sales.shipping.origin.address') }}</div>
                            <div>{{ core()->getConfigData('sales.shipping.origin.zipcode') . ' ' . core()->getConfigData('sales.shipping.origin.city') }}</div>
                            <div>{{ core()->getConfigData('sales.shipping.origin.state') . ', ' . $originCountry }}</div>
                        </td>
                    @endif
                    @if ($invoice->hasPaymentTerm() || $bankDetails)
                        <td style="width: 50%;">
                            @if ($invoice->hasPaymentTerm())
                                <div>
                                    <strong>@lang('shop::app.customers.account.orders.invoice-pdf.payment-terms')</strong>
                                    {{ $invoice->getFormattedPaymentTerm() }}
                                </div>
                            @endif
                            @if ($bankDetails)
                                <div><strong>@lang('shop::app.customers.account.orders.invoice-pdf.bank-details')</strong></div>
                                <div>{!! nl2br(e($bankDetails)) !!}</div>
                            @endif
                        </td>
                    @endif
                </tr>
            </table>
        @endif

        @php
            $addresses = array_filter([
                'bill-to' => $order->billing_address,
                'ship-to' => $order->shipping_address,
            ]);
        @endphp

        @if ($addresses)
            <table class="details">
                <thead>
                    <tr>
                        @foreach ($addresses as $label => $address)
                            <th scope="col" style="width: {{ 100 / count($addresses) }}%;">{{ trans('shop::app.customers.account.orders.invoice-pdf.' . $label) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach ($addresses as $address)
                            <td>
                                <strong>{{ $address->name }}</strong>
                                @if ($address->company_name)
                                    <div>{{ $address->company_name }}</div>
                                @endif
                                <div>{!! nl2br(e($address->address)) !!}</div>
                                <div>{{ trim($address->postcode . ' ' . $address->city) }}</div>
                                <div>{{ implode(', ', array_filter([$address->state, core()->countryName($address->country)])) }}</div>
                                @if ($address->phone)
                                    <div>@lang('shop::app.customers.account.orders.invoice-pdf.phone'): <span dir="ltr">{{ $address->phone }}</span></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @endif

        <!-- Payment & Shipping Methods -->
        <table class="details">
            <thead>
                <tr>
                    <th scope="col">
                        <b>
                            @lang('shop::app.customers.account.orders.invoice-pdf.payment-method')
                        </b>
                    </th>

                    @if ($order->shipping_address)
                        <th scope="col" style="width: 50%;">
                            <b>
                                @lang('shop::app.customers.account.orders.invoice-pdf.shipping-method')
                            </b>
                        </th>
                    @endif
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>
                        {{ core()->getConfigData('sales.payment_methods.' . $order->payment->method . '.title') }}

                        @php $additionalDetails = \Webkul\Payment\Payment::getAdditionalDetails($order->payment->method); @endphp

                        @if (! empty($additionalDetails))
                            <div class="row small-text">
                                <span>{{ $additionalDetails['title'] }}:</span>

                                <span>{{ $additionalDetails['value'] }}</span>
                            </div>
                        @endif
                    </td>

                    @if ($order->shipping_address)
                        <td style="width: 50%">
                            {{ $order->shipping_title }}
                        </td>
                    @endif
                </tr>
            </tbody>
        </table>

        <!-- Items -->
        <table class="items">
            <thead>
                <tr>
                    <th scope="col" class="sku" style="width: 17%;">
                        @lang('shop::app.customers.account.orders.invoice-pdf.sku')
                    </th>

                    <th scope="col" style="width: 38%;">
                        @lang('shop::app.customers.account.orders.invoice-pdf.product-name')
                    </th>

                    <th scope="col" class="amount" style="width: 19%;">
                        @lang('shop::app.customers.account.orders.invoice-pdf.price')
                    </th>

                    <th scope="col" class="quantity" style="width: 7%;">
                        @lang('shop::app.customers.account.orders.invoice-pdf.qty')
                    </th>

                    <th scope="col" class="amount" style="width: 19%;">
                        @lang('shop::app.customers.account.orders.invoice-pdf.subtotal')
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="sku">
                            {{ $item->getTypeInstance()->getOrderedItem($item)->sku }}
                        </td>

                        <td>
                            <strong>{{ $item->name }}</strong>

                            @if (isset($item->additional['attributes']))
                                <div class="small-text">
                                    @foreach ($item->additional['attributes'] as $attribute)
                                        @if (
                                            ! isset($attribute['attribute_type'])
                                            || $attribute['attribute_type'] !== 'file'
                                        )
                                            <b>{{ $attribute['attribute_name'] }} : </b>{{ $attribute['option_label'] }}<br>
                                        @else
                                            {{ $attribute['attribute_name'] }} :

                                            <a
                                                href="{{ Storage::url($attribute['option_label']) }}"
                                                download="{{ File::basename($attribute['option_label']) }}"
                                            >
                                                {{ File::basename($attribute['option_label']) }}
                                            </a>

                                            <br>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>

                        <td class="amount">
                            @if (core()->getConfigData('sales.taxes.sales.display_prices') == 'including_tax')
                                {{ core()->formatPrice($item->price_incl_tax, $orderCurrencyCode) }}
                            @elseif (core()->getConfigData('sales.taxes.sales.display_prices') == 'both')
                                {{ core()->formatPrice($item->price_incl_tax, $orderCurrencyCode) }}

                                <div class="small-text">
                                    @lang('shop::app.customers.account.orders.invoice-pdf.excl-tax')

                                    <span>
                                        {{ core()->formatPrice($item->price, $orderCurrencyCode) }}
                                    </span>
                                </div>
                            @else
                                {{ core()->formatPrice($item->price, $orderCurrencyCode) }}
                            @endif
                        </td>

                        <td class="quantity">
                            {{ $item->qty }}
                        </td>

                        <td class="amount">
                            @if (core()->getConfigData('sales.taxes.sales.display_subtotal') == 'including_tax')
                                {{ core()->formatPrice($item->total_incl_tax, $orderCurrencyCode) }}
                            @elseif (core()->getConfigData('sales.taxes.sales.display_subtotal') == 'both')
                                {{ core()->formatPrice($item->total_incl_tax, $orderCurrencyCode) }}

                                <div class="small-text">
                                    @lang('shop::app.customers.account.orders.invoice-pdf.excl-tax')

                                    <span>
                                        {{ core()->formatPrice($item->total, $orderCurrencyCode) }}
                                    </span>
                                </div>
                            @else
                                {{ core()->formatPrice($item->total, $orderCurrencyCode) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Summary Table -->
        <table class="summary" align="{{ $end }}">
            <tbody>
                @if (core()->getConfigData('sales.taxes.sales.display_subtotal') == 'including_tax')
                    <tr>
                        <td>@lang('shop::app.customers.account.orders.invoice-pdf.subtotal')</td>
                        <td class="amount">{{ core()->formatPrice($invoice->sub_total_incl_tax, $orderCurrencyCode) }}</td>
                    </tr>
                @elseif (core()->getConfigData('sales.taxes.sales.display_subtotal') == 'both')
                    <tr>
                        <td>@lang('shop::app.customers.account.orders.invoice-pdf.subtotal-incl-tax')</td>
                        <td class="amount">{{ core()->formatPrice($invoice->sub_total_incl_tax, $orderCurrencyCode) }}</td>
                    </tr>

                    <tr>
                        <td>@lang('shop::app.customers.account.orders.invoice-pdf.subtotal-excl-tax')</td>
                        <td class="amount">{{ core()->formatPrice($invoice->sub_total, $orderCurrencyCode) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>@lang('shop::app.customers.account.orders.invoice-pdf.subtotal')</td>
                        <td class="amount">{{ core()->formatPrice($invoice->sub_total, $orderCurrencyCode) }}</td>
                    </tr>
                @endif

                @if (core()->getConfigData('sales.taxes.sales.display_shipping_amount') == 'including_tax')
                    <tr>
                        <td>@lang('shop::app.customers.account.orders.invoice-pdf.shipping-handling')</td>
                        <td class="amount">{{ core()->formatPrice($invoice->shipping_amount_incl_tax, $orderCurrencyCode) }}</td>
                    </tr>
                @elseif (core()->getConfigData('sales.taxes.sales.display_shipping_amount') == 'both')
                    <tr>
                        <td>@lang('shop::app.customers.account.orders.invoice-pdf.shipping-handling-incl-tax')</td>
                        <td class="amount">{{ core()->formatPrice($invoice->shipping_amount_incl_tax, $orderCurrencyCode) }}</td>
                    </tr>

                    <tr>
                        <td>@lang('shop::app.customers.account.orders.invoice-pdf.shipping-handling-excl-tax')</td>
                        <td class="amount">{{ core()->formatPrice($invoice->shipping_amount, $orderCurrencyCode) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>@lang('shop::app.customers.account.orders.invoice-pdf.shipping-handling')</td>
                        <td class="amount">{{ core()->formatPrice($invoice->shipping_amount, $orderCurrencyCode) }}</td>
                    </tr>
                @endif

                <tr>
                    <td>@lang('shop::app.customers.account.orders.invoice-pdf.tax')</td>
                    <td class="amount">{{ core()->formatPrice($invoice->tax_amount, $orderCurrencyCode) }}</td>
                </tr>

                <tr>
                    <td>@lang('shop::app.customers.account.orders.invoice-pdf.discount')</td>
                    <td class="amount">{{ core()->formatPrice($invoice->discount_amount, $orderCurrencyCode) }}</td>
                </tr>

                <tr class="grand-total">
                    <td>
                        <b>@lang('shop::app.customers.account.orders.invoice-pdf.grand-total')</b>
                    </td>
                    <td class="amount">
                        <b>{{ core()->formatPrice($invoice->grand_total, $orderCurrencyCode) }}</b>
                    </td>
                </tr>
            </tbody>
        </table>
    </body>
</html>
