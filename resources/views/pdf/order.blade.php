<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - #{{ $order->order_number ?? $order->id }} - Grass Florist</title>

    @php
        $settings = \App\Models\GlobalSetting::current();
        $trn = $settings->vat_registration_number ?? '300553485900003';
        $vatRate = (float)($settings->vat_percentage ?? 15.00);

        $subtotal = (float)$order->subtotal;
        $shipping = (float)($order->shipping_amount ?? 30.00);
        $tax = (float)($order->tax_amount ?? ($subtotal * ($vatRate / 100)));
        $total = (float)($order->total_amount ?: ($subtotal + $shipping + $tax));

        // Invoice Number format: INV/YYYY/OrderNumber/ID
        $year = $order->created_at ? $order->created_at->format('Y') : date('Y');
        $orderNum = $order->order_number ?: $order->id;
        $invoiceNumber = $order->invoice_number ?: "INV/{$year}/{$orderNum}/{$order->id}";

        // Format dates
        $invoiceDate = $order->created_at ? $order->created_at->format('F d, Y') : date('F d, Y');
        
        $arMonths = [
            'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس', 'April' => 'أبريل',
            'May' => 'مايو', 'June' => 'يونيو', 'July' => 'يوليو', 'August' => 'أغسطس',
            'September' => 'سبتمبر', 'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر'
        ];
        $monthEn = $order->created_at ? $order->created_at->format('F') : date('F');
        $monthAr = $arMonths[$monthEn] ?? $monthEn;
        $day = $order->created_at ? $order->created_at->format('d') : date('d');
        $createdYear = $order->created_at ? $order->created_at->format('Y') : date('Y');
        $timeStr = $order->created_at ? $order->created_at->format('h:i') : date('h:i');
        $ampmStr = ($order->created_at ? $order->created_at->format('A') : date('A')) === 'AM' ? 'ص' : 'م';
        $orderDateFormatted = "{$monthAr} {$day}, {$createdYear} {$timeStr} {$ampmStr}";

        $deliveryDateFormatted = 'N/A';
        if (!empty($order->delivery_date)) {
            try {
                $deliveryDateFormatted = \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d');
            } catch (\Exception $e) {
                $deliveryDateFormatted = substr((string)$order->delivery_date, 0, 10);
            }
        }
        $deliveryTimeFormatted = !empty($order->delivery_time) ? $order->delivery_time : 'من 11 صباحاً وحتى 3 مساءً';

        $shippingAddressFormatted = trim(($order->address ?? '') . ' ' . ($order->address_2 ?? '') . ' ' . ($order->city ?? ''));
        if (empty($shippingAddressFormatted)) {
            $shippingAddressFormatted = $order->city ?: 'Jeddah';
        }

        $methodRaw = strtolower((string)($order->payment_method ?? ''));
        $paymentMethodAr = match(true) {
            str_contains($methodRaw, 'apple') => 'ابل باي',
            str_contains($methodRaw, 'mada') => 'مدى',
            str_contains($methodRaw, 'stc') => 'اس تي سي باي',
            str_contains($methodRaw, 'tabby') => 'تابي',
            str_contains($methodRaw, 'tamara') => 'تمارا',
            str_contains($methodRaw, 'cod') => 'الدفع عند الاستلام',
            str_contains($methodRaw, 'card') || str_contains($methodRaw, 'visa') || str_contains($methodRaw, 'master') => 'بطاقة ائتمانية',
            default => !empty($order->payment_method) ? $order->payment_method : 'ابل باي',
        };

        // ZATCA Phase-1 Compliant QR Code
        $sellerName = 'مؤسسة غراس السعودية';
        $qrUrl = zatca_qr_image_url(
            sellerName: $sellerName,
            trn: $trn,
            timestamp: $order->created_at ?? now(),
            totalAmount: $total,
            vatAmount: $tax,
            size: 150
        );

        $logoPath = public_path('images/grass-logo.webp');
        $logoSrc = file_exists($logoPath) ? ('data:image/webp;base64,' . base64_encode(file_get_contents($logoPath))) : asset('images/grass-logo.webp');
    @endphp

    <style>
        @page {
            size: a4 portrait;
            margin: 10mm 12mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            background-color: #ffffff;
            color: #000000;
            font-size: 11px;
            line-height: 1.35;
            padding: 10px;
        }

        /* Header Table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo-col {
            width: 25%;
            text-align: left;
        }

        .logo-img {
            width: 100px;
            height: auto;
        }

        .qr-col {
            width: 35%;
            text-align: center;
        }

        .qr-img {
            width: 115px;
            height: 115px;
        }

        .company-col {
            width: 40%;
            text-align: right;
            font-size: 10.5px;
            line-height: 1.4;
            color: #000;
        }

        .company-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .invoice-main-title {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            margin-bottom: 4px;
        }

        .invoice-number-tag {
            font-size: 13px;
            font-weight: bold;
            color: #000;
            text-align: center;
            margin-bottom: 10px;
        }

        /* 2x2 Metadata Grid */
        .meta-grid-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #444;
            margin-bottom: 15px;
        }

        .meta-grid-table > tbody > tr > td {
            border: 1px solid #444;
            vertical-align: top;
            padding: 6px 10px;
            font-size: 10.5px;
        }

        .box-heading {
            font-size: 11.5px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .meta-row {
            margin-bottom: 2px;
        }

        /* Products Table */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #000;
        }

        .products-table thead th {
            background-color: #000000;
            color: #ffffff;
            padding: 7px 6px;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
            white-space: nowrap;
        }

        .products-table tbody td {
            padding: 8px 6px;
            text-align: center;
            vertical-align: middle;
            font-size: 11.5px;
            border-bottom: 1px solid #ddd;
        }

        .prod-thumb {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 4px;
        }

        .prod-code {
            font-size: 13px;
            font-weight: bold;
        }

        .sar-symbol {
            color: #689f38;
            font-weight: bold;
        }

        /* Totals Summary Table */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .totals-table tr {
            border-top: 1px solid #e0e0e0;
        }

        .totals-table td {
            padding: 6px 8px;
            font-size: 11px;
        }

        .tot-en {
            width: 25%;
            text-align: left;
            font-weight: bold;
        }

        .tot-ar {
            width: 45%;
            text-align: center;
            font-weight: bold;
            direction: rtl;
        }

        .tot-val {
            width: 30%;
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }

        .tot-grand {
            border-top: 1.5px solid #000 !important;
            border-bottom: 2px solid #000 !important;
            font-size: 12.5px !important;
            font-weight: bold !important;
        }
    </style>
</head>
<body>

    <!-- Header: Logo | QR | Store Details -->
    <table class="header-table">
        <tr>
            <td class="logo-col">
                <img src="{{ $logoSrc }}" class="logo-img" alt="Grass Florist">
            </td>
            <td class="qr-col">
                <img src="{{ $qrUrl }}" class="qr-img" alt="ZATCA Tax QR Code">
            </td>
            <td class="company-col">
                <div class="company-title">مؤسسة غراس السعودية – GRASS Saudi Est</div>
                <div>Address: شارع الكيال – حي الروضة</div>
                <div>Jeddah, Saudi Arabia</div>
                <div>WhatsApp: +966555134211</div>
                <div>CR No: 4030227933</div>
                <div>VAT No: {{ $trn }}</div>
                <div>E-mail: info@grassflorist.com</div>
                <div>Web: https://grassflorist.com</div>
            </td>
        </tr>
    </table>

    <div class="invoice-main-title">INVOICE - فاتورة</div>

    <div class="invoice-number-tag">
        {{ $invoiceNumber }}
    </div>

    <!-- 2x2 Metadata Grid -->
    <table class="meta-grid-table">
        <tr>
            <!-- Customer Details -->
            <td style="width: 50%;">
                <div class="box-heading">Customer details:</div>
                <div class="meta-row"><strong>First name:</strong> {{ $order->first_name ?: 'N/A' }}</div>
                <div class="meta-row"><strong>Last name:</strong> {{ $order->last_name ?: '-' }}</div>
                <div class="meta-row"><strong>Email:</strong> {{ $order->email ?: '-' }}</div>
                <div class="meta-row"><strong>Phone number:</strong> {{ $order->customer_phone ?: '-' }}</div>
            </td>

            <!-- Invoice Details (3 Columns) -->
            <td style="width: 50%; padding: 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #666;">
                        <td style="padding: 5px 8px; width: 32%; text-align: left; font-weight: bold;">Invoice Date</td>
                        <td style="padding: 5px 8px; width: 38%; text-align: center; font-weight: bold;">{{ $invoiceDate }}</td>
                        <td style="padding: 5px 8px; width: 30%; text-align: right; direction: rtl;">تاريخ الفاتورة</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #666;">
                        <td style="padding: 5px 8px; text-align: left; font-weight: bold;">Order number</td>
                        <td style="padding: 5px 8px; text-align: center; font-weight: bold;">{{ $orderNum }}</td>
                        <td style="padding: 5px 8px; text-align: right; direction: rtl;">رقم الطلب</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #666;">
                        <td style="padding: 5px 8px; text-align: left; font-weight: bold;">Order date</td>
                        <td style="padding: 5px 8px; text-align: center; font-size: 10px;">{{ $orderDateFormatted }}</td>
                        <td style="padding: 5px 8px; text-align: right; direction: rtl;">تاريخ الطلب</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 8px; text-align: left; font-weight: bold;">Payment method</td>
                        <td style="padding: 5px 8px; text-align: center; font-weight: bold;">{{ $paymentMethodAr }}</td>
                        <td style="padding: 5px 8px; text-align: right; direction: rtl;">طريقة الدفع</td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <!-- Receiver Details -->
            <td style="width: 50%;">
                <div class="box-heading">Receiver details:</div>
                <div class="meta-row"><strong>First name:</strong> {{ $order->recipient_name ?: $order->first_name }}</div>
                <div class="meta-row"><strong>Last name:</strong> {{ $order->recipient_last_name ?: $order->last_name }}</div>
                <div class="meta-row"><strong>Phone number:</strong> {{ $order->recipient_phone ?: $order->customer_phone }}</div>
                <div class="meta-row"><strong>Shipping address:</strong> {{ $shippingAddressFormatted }}</div>
            </td>

            <!-- Delivery Date Details -->
            <td style="width: 50%;">
                <div class="box-heading">Delivery date - تاريخ التوصيل</div>
                <div class="meta-row"><strong>Delivery date:</strong> {{ $deliveryDateFormatted }}</div>
                <div class="meta-row"><strong>Delivery time:</strong> {{ $deliveryTimeFormatted }}</div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table (Black Header) -->
    <table class="products-table">
        <thead>
            <tr>
                <th style="width: 12%;">Image - الصورة</th>
                <th style="width: 25%;">Product - المنتج</th>
                <th style="width: 22%;">Category - الفئة</th>
                <th style="width: 15%;">Quantity - الكمية</th>
                <th style="width: 26%;">Price/Unit - السعر/الوحدة</th>
            </tr>
        </thead>
        <tbody>
            @php
                $orderItems = $items ?? $order->items ?? collect();
            @endphp
            @forelse($orderItems as $item)
                @php
                    $prod = $item->product;
                    $thumb = $prod?->thumbnail_image ? public_path('storage/' . $prod->thumbnail_image) : null;
                    if ($thumb && !file_exists($thumb)) { $thumb = null; }
                    
                    $prodDisplay = $prod?->sku;
                    if (empty($prodDisplay)) {
                        $rawProdName = $item->product_name ?: ($prod ? $prod->name : null);
                        if (is_array($rawProdName)) {
                            $prodDisplay = $rawProdName['en'] ?? $rawProdName['ar'] ?? reset($rawProdName);
                        } else {
                            $prodDisplay = (string)($rawProdName ?: 'G408');
                        }
                    }

                    $rawCat = $prod?->category?->name ?: ($prod?->categories?->first()?->name ?: null);
                    if (is_array($rawCat)) {
                        $catName = $rawCat['ar'] ?? $rawCat['en'] ?? reset($rawCat);
                    } elseif (is_string($rawCat)) {
                        $catName = $rawCat;
                    } else {
                        $catName = '';
                    }

                    $unitPrice = (float)$item->price;
                    $formattedPrice = (floor($unitPrice) == $unitPrice) ? number_format($unitPrice, 0) : number_format($unitPrice, 2);
                @endphp
                <tr>
                    <td>
                        @if($thumb)
                            <img src="{{ $thumb }}" class="prod-thumb" alt="Product">
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        <div class="prod-code">{{ $prodDisplay }}</div>
                        @if(!empty($item->variation_details))
                            <div style="font-size: 9px; color: #666;">{{ $item->variation_details }}</div>
                        @endif
                    </td>
                    <td>
                        {{ $catName }}
                    </td>
                    <td>
                        <strong>{{ $item->quantity }}</strong>
                    </td>
                    <td>
                        <span class="sar-symbol">﷼</span>{{ $formattedPrice }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td>-</td>
                    <td><div class="prod-code">G408</div></td>
                    <td>-</td>
                    <td><strong>1</strong></td>
                    <td><span class="sar-symbol">﷼</span>{{ (floor($subtotal) == $subtotal) ? number_format($subtotal, 0) : number_format($subtotal, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Totals Summary Table -->
    @php
        $fmtSubtotal = (floor($subtotal) == $subtotal) ? number_format($subtotal, 0) : number_format($subtotal, 2);
        $fmtTax = (floor($tax) == $tax) ? number_format($tax, 0) : number_format($tax, 2);
        $fmtShipping = (floor($shipping) == $shipping) ? number_format($shipping, 0) : number_format($shipping, 2);
        $fmtTotal = (floor($total) == $total) ? number_format($total, 0) : number_format($total, 2);
        $shippingMethodTitle = $order->shipping_method_name ?: 'Delivery rate inside Jeddah';
    @endphp
    <table class="totals-table">
        <tr>
            <td class="tot-en">Payment method</td>
            <td class="tot-ar">ع طريقة الدفع اي السداد</td>
            <td class="tot-val">{{ $paymentMethodAr }}</td>
        </tr>
        <tr>
            <td class="tot-en">Subtotal</td>
            <td class="tot-ar">الاجمالي قبل ضريبة القيمة المضافة</td>
            <td class="tot-val"><span class="sar-symbol">﷼</span>{{ $fmtSubtotal }}</td>
        </tr>
        <tr>
            <td class="tot-en">Saudi Arabia VAT (15%)</td>
            <td class="tot-ar">ضريبة القيمة المضافة في المملكة العربية السعودية (15%)</td>
            <td class="tot-val"><span class="sar-symbol">﷼</span>{{ $fmtTax }}</td>
        </tr>
        <tr>
            <td class="tot-en">Shipping</td>
            <td class="tot-ar">شحن</td>
            <td class="tot-val"><span class="sar-symbol">﷼</span>{{ $fmtShipping }} بواسطة {{ $shippingMethodTitle }}</td>
        </tr>
        <tr class="tot-grand">
            <td class="tot-en">Total</td>
            <td class="tot-ar">الاجمالي</td>
            <td class="tot-val"><span class="sar-symbol">﷼</span>{{ $fmtTotal }}</td>
        </tr>
    </table>

</body>
</html>
