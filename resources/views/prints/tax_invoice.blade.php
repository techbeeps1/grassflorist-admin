<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - #{{ $order->order_number ?? $order->id }} - Grass Florist</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">

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
        
        // Exact Arabic Month mapping for Order Date (Matching: أكتوبر 01, 2026 12:00 م)
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

        // Clean Delivery Date (No 00:00:00)
        $deliveryDateFormatted = 'N/A';
        if (!empty($order->delivery_date)) {
            try {
                $deliveryDateFormatted = \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d');
            } catch (\Exception $e) {
                $deliveryDateFormatted = substr((string)$order->delivery_date, 0, 10);
            }
        }
        $deliveryTimeFormatted = !empty($order->delivery_time) ? $order->delivery_time : 'من 11 صباحاً وحتى 3 مساءً';

        // Shipping address clean string
        $shippingAddressFormatted = trim(($order->address ?? '') . ' ' . ($order->address_2 ?? '') . ' ' . ($order->city ?? ''));
        if (empty($shippingAddressFormatted)) {
            $shippingAddressFormatted = $order->city ?: 'Jeddah';
        }

        // Arabic Payment Method mapping
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

        // Logo Base64
        $logoPath = public_path('images/grass-logo.webp');
        $logoSrc = file_exists($logoPath) ? ('data:image/webp;base64,' . base64_encode(file_get_contents($logoPath))) : asset('images/grass-logo.webp');
    @endphp

    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: #f3f4f6;
            color: #000000;
            margin: 0;
            padding: 0 0 40px 0;
            font-size: 12px;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Screen Action Bar (Sticky at top so it never overlaps the document) */
        .toolbar-wrapper {
            position: sticky;
            top: 0;
            z-index: 9999;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .btn {
            background: #059669;
            color: #ffffff;
            border: none;
            padding: 8px 20px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover { background: #047857; }
        .btn-secondary { background: #4b5563; }
        .btn-secondary:hover { background: #374151; }

        /* Invoice Paper Container (A4 Proportions) */
        .invoice-wrapper {
            max-width: 820px;
            margin: 25px auto 20px auto;
            background: #ffffff;
            padding: 30px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
        }

        /* Header Section */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo-col {
            width: 25%;
            text-align: left;
        }

        .logo-img {
            width: 110px;
            height: auto;
            display: block;
        }

        .qr-col {
            width: 35%;
            text-align: center;
        }

        .qr-img {
            width: 120px;
            height: 120px;
            display: inline-block;
        }

        .company-col {
            width: 40%;
            text-align: right;
            font-size: 11px;
            line-height: 1.45;
            color: #000;
        }

        .company-title {
            font-size: 13px;
            font-weight: 700;
            color: #000;
            margin-bottom: 3px;
        }

        /* Invoice Title Bar */
        .invoice-main-title {
            font-size: 22px;
            font-weight: 800;
            color: #000000;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .invoice-number-tag {
            font-size: 15px;
            font-weight: 700;
            color: #000000;
            font-family: monospace, 'Courier New', sans-serif;
            text-align: center;
            width: 100%;
            margin-bottom: 12px;
        }

        /* 2x2 Metadata Grid */
        .meta-grid-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #333333;
            margin-bottom: 22px;
        }

        .meta-grid-table > tbody > tr > td {
            border: 1px solid #444444;
            vertical-align: top;
            font-size: 12px;
            color: #000;
        }

        .box-heading {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #000;
        }

        .meta-row {
            margin-bottom: 3px;
            line-height: 1.35;
        }

        /* Products Table */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
            border: 1px solid #000000;
        }

        .products-table thead th {
            background-color: #000000;
            color: #ffffff;
            padding: 9px 8px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            border: 1px solid #000000;
            white-space: nowrap;
        }

        .products-table tbody td {
            padding: 10px 8px;
            text-align: center;
            vertical-align: middle;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
            color: #000;
        }

        .prod-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            display: inline-block;
        }

        .prod-code {
            font-size: 15px;
            font-weight: 700;
            color: #000;
        }

        .prod-qty {
            font-size: 15px;
            font-weight: 600;
            color: #000;
        }

        /* Currency SAR Symbol (Green character matching live invoice) */
        .sar-symbol {
            display: inline-block;
            color: #689f38;
            font-weight: 700;
            font-size: 14px;
            margin-right: 2px;
        }

        /* Totals Summary Table */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .totals-table tr {
            border-top: 1px solid #e0e0e0;
        }

        .totals-table td {
            padding: 7px 10px;
            font-size: 12.5px;
            color: #000;
        }

        .tot-en {
            width: 25%;
            text-align: left;
            font-weight: 600;
        }

        .tot-ar {
            width: 45%;
            text-align: center;
            font-weight: 600;
            direction: rtl;
        }

        .tot-val {
            width: 30%;
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }

        .tot-grand {
            border-top: 1.5px solid #000000 !important;
            border-bottom: 2px solid #000000 !important;
            font-size: 14px !important;
            font-weight: 800 !important;
        }

        @media print {
            body {
                background: none !important;
                padding: 0 !important;
            }

            .toolbar-wrapper {
                display: none !important;
            }

            .invoice-wrapper {
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            .products-table thead th {
                background-color: #000000 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Toolbar (Sticky, non-overlapping) -->
    <div class="toolbar-wrapper">
        <button class="btn" onclick="window.print()">
            Print Tax Invoice
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            Close
        </button>
    </div>

    <!-- Printable Invoice Page -->
    <div class="invoice-wrapper">

        <!-- Top Header: Logo | QR | Store Details -->
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

        <!-- Main Title & Invoice Number -->
        <div class="invoice-main-title">INVOICE - فاتورة</div>

        <div class="invoice-number-tag">
            {{ $invoiceNumber }}
        </div>

        <!-- 2x2 Metadata Grid (Customer, Invoice, Receiver, Delivery) -->
        <table class="meta-grid-table">
            <!-- Row 1 -->
            <tr>
                <!-- Customer Details -->
                <td style="width: 50%; padding: 10px 14px;">
                    <div class="box-heading">Customer details:</div>
                    <div class="meta-row"><strong>First name:</strong> {{ $order->first_name ?: 'N/A' }}</div>
                    <div class="meta-row"><strong>Last name:</strong> {{ $order->last_name ?: '-' }}</div>
                    <div class="meta-row"><strong>Email:</strong> {{ $order->email ?: '-' }}</div>
                    <div class="meta-row"><strong>Phone number:</strong> {{ $order->customer_phone ?: '-' }}</div>
                </td>

                <!-- Invoice Details (3 Columns) -->
                <td style="width: 50%; padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #666666;">
                            <td style="padding: 6px 10px; width: 32%; text-align: left; font-weight: 500;">Invoice Date</td>
                            <td style="padding: 6px 10px; width: 38%; text-align: center; font-weight: 600;">{{ $invoiceDate }}</td>
                            <td style="padding: 6px 10px; width: 30%; text-align: right; font-weight: 600; direction: rtl;">تاريخ الفاتورة</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #666666;">
                            <td style="padding: 6px 10px; text-align: left; font-weight: 500;">Order number</td>
                            <td style="padding: 6px 10px; text-align: center; font-weight: 600;">{{ $orderNum }}</td>
                            <td style="padding: 6px 10px; text-align: right; font-weight: 600; direction: rtl;">رقم الطلب</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #666666;">
                            <td style="padding: 6px 10px; text-align: left; font-weight: 500;">Order date</td>
                            <td style="padding: 6px 10px; text-align: center; font-weight: 600; font-size: 11px;">{{ $orderDateFormatted }}</td>
                            <td style="padding: 6px 10px; text-align: right; font-weight: 600; direction: rtl;">تاريخ الطلب</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 10px; text-align: left; font-weight: 500;">Payment method</td>
                            <td style="padding: 6px 10px; text-align: center; font-weight: 600;">{{ $paymentMethodAr }}</td>
                            <td style="padding: 6px 10px; text-align: right; font-weight: 600; direction: rtl;">طريقة الدفع</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <!-- Row 2 -->
            <tr>
                <!-- Receiver Details -->
                <td style="width: 50%; padding: 10px 14px;">
                    <div class="box-heading">Receiver details:</div>
                    <div class="meta-row"><strong>First name:</strong> {{ $order->recipient_name ?: $order->first_name }}</div>
                    <div class="meta-row"><strong>Last name:</strong> {{ $order->recipient_last_name ?: $order->last_name }}</div>
                    <div class="meta-row"><strong>Phone number:</strong> {{ $order->recipient_phone ?: $order->customer_phone }}</div>
                    <div class="meta-row"><strong>Shipping address:</strong> {{ $shippingAddressFormatted }}</div>
                </td>

                <!-- Delivery Date Details -->
                <td style="width: 50%; padding: 10px 14px;">
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
                    $orderItems = $order->items ?? collect();
                @endphp
                @forelse($orderItems as $item)
                    @php
                        $prod = $item->product;
                        $thumb = $prod?->thumbnail_image ? asset('storage/' . $prod->thumbnail_image) : null;
                        
                        // Safe SKU or Name
                        $prodDisplay = $prod?->sku;
                        if (empty($prodDisplay)) {
                            $rawProdName = $item->product_name ?: ($prod ? $prod->name : null);
                            if (is_array($rawProdName)) {
                                $prodDisplay = $rawProdName['en'] ?? $rawProdName['ar'] ?? reset($rawProdName);
                            } else {
                                $prodDisplay = (string)($rawProdName ?: 'G408');
                            }
                        }

                        // Safe Category
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
                                <div style="width: 48px; height: 48px; background: #f3f4f6; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; color: #9ca3af; border: 1px solid #d1d5db;">Flower</div>
                            @endif
                        </td>
                        <td>
                            <div class="prod-code">{{ $prodDisplay }}</div>
                            @if(!empty($item->variation_details))
                                <div style="font-size: 10px; color: #6b7280;">{{ $item->variation_details }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $catName }}
                        </td>
                        <td>
                            <span class="prod-qty">{{ $item->quantity }}</span>
                        </td>
                        <td>
                            <span class="sar-symbol">﷼</span>{{ $formattedPrice }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td>
                            <div style="width: 48px; height: 48px; background: #f3f4f6; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; color: #9ca3af; border: 1px solid #d1d5db;">Flower</div>
                        </td>
                        <td>
                            <div class="prod-code">G408</div>
                        </td>
                        <td>-</td>
                        <td><span class="prod-qty">1</span></td>
                        <td><span class="sar-symbol">﷼</span>{{ (floor($subtotal) == $subtotal) ? number_format($subtotal, 0) : number_format($subtotal, 2) }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Totals & Financial Breakdown -->
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
            @if(strtoupper($order->currency ?? 'SAR') === 'USD' && $order->currency_amount)
            <tr style="background: #f0fdf4; font-weight: 700;">
                <td class="tot-en" style="color: #15803d;">Paid in USD</td>
                <td class="tot-ar" style="color: #15803d;">المبلغ المدفوع بالدولار</td>
                <td class="tot-val" style="color: #15803d; font-size: 13px;">${{ number_format((float)$order->currency_amount, 2) }} USD (Rate: 1 SAR = {{ number_format((float)($order->exchange_rate ?? 0.2667), 4) }} USD)</td>
            </tr>
            @endif
        </table>

    </div>

</body>
</html>
