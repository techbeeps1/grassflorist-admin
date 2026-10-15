<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0, user-scalable=yes">
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
        $isArOrder = ($order->order_language === 'ar');
        $deliveryTimeRaw = trim((string)($order->delivery_time ?? ''));

        $resolvedSlot = null;
        if (!empty($deliveryTimeRaw)) {
            $resolvedSlot = \App\Models\DeliverySlot::where('start_time', $deliveryTimeRaw)
                ->orWhere('id', $deliveryTimeRaw)
                ->orWhere('title_en', $deliveryTimeRaw)
                ->orWhere('title_ar', $deliveryTimeRaw)
                ->first();

            if (!$resolvedSlot) {
                if (str_starts_with($deliveryTimeRaw, '11:00')) {
                    $resolvedSlot = \App\Models\DeliverySlot::where('start_time', '11:00:00')->first();
                } elseif (str_starts_with($deliveryTimeRaw, '19:00') || str_starts_with($deliveryTimeRaw, '07:00')) {
                    $resolvedSlot = \App\Models\DeliverySlot::where('start_time', '19:00:00')->first();
                } elseif (str_starts_with($deliveryTimeRaw, '16:00') || str_starts_with($deliveryTimeRaw, '04:00')) {
                    $resolvedSlot = \App\Models\DeliverySlot::where('start_time', '16:00:00')->first();
                } elseif (str_starts_with($deliveryTimeRaw, '19:30') || str_starts_with($deliveryTimeRaw, '07:30')) {
                    $resolvedSlot = \App\Models\DeliverySlot::where('start_time', '19:30:00')->first();
                }
            }
        }

        if ($resolvedSlot) {
            $deliveryTimeFormatted = $isArOrder
                ? ($resolvedSlot->title_ar ?: $resolvedSlot->title_en)
                : ($resolvedSlot->title_en ?: $resolvedSlot->title_ar);
        } elseif (!empty($deliveryTimeRaw) && !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $deliveryTimeRaw)) {
            $deliveryTimeFormatted = $deliveryTimeRaw;
        } else {
            $deliveryTimeFormatted = $isArOrder ? 'من 11 صباحاً وحتى 3 مساءً' : '11:00 AM to 03:00 PM';
        }

        // Shipping address clean string
        $shippingAddressFormatted = trim(($order->address ?? '') . ' ' . ($order->address_2 ?? '') . ' ' . ($order->city ?? ''));
        if (empty($shippingAddressFormatted)) {
            $shippingAddressFormatted = $order->city ?: 'Jeddah';
        }

        // Friendly Customer Payment Method mapping (No technical gateway names like hyperpay)
        $methodRaw = strtolower((string)($order->payment_method ?? ''));
        $meta = is_array($order->meta_data) ? $order->meta_data : (json_decode($order->meta_data, true) ?: []);
        $gatewayMeta = strtolower((string)($meta['payment_gateway'] ?? $meta['gateway'] ?? $meta['payment_method'] ?? $meta['brand'] ?? ''));
        $combo = $methodRaw . ' ' . $gatewayMeta;

        $paymentMethodDisplay = match(true) {
            str_contains($combo, 'apple') => 'أبل باي (Apple Pay)',
            str_contains($combo, 'mada') => 'بطاقة مدى (Mada Card)',
            str_contains($combo, 'stc') => 'اس تي سي باي (STC Pay)',
            str_contains($combo, 'tabby') => 'تابي (Tabby)',
            str_contains($combo, 'tamara') => 'تمارا (Tamara)',
            str_contains($combo, 'cod') || str_contains($combo, 'cash') => 'الدفع عند الاستلام (Cash on Delivery)',
            str_contains($combo, 'paypal') || str_contains($combo, 'ppcp') => 'بايبال (PayPal)',
            str_contains($combo, 'visa') => 'بطاقة فيزا (Visa Card)',
            str_contains($combo, 'master') => 'ماستركارد (MasterCard)',
            str_contains($combo, 'hyperpay') || str_contains($combo, 'card') => 'بطاقة ائتمان (Card)',
            !empty($order->payment_method) && !str_contains($methodRaw, 'gateway') && !str_contains($methodRaw, 'hyperpay') => ucwords(str_replace(['_', '-'], ' ', $order->payment_method)),
            default => 'بطاقة ائتمان (Card)',
        };
        $paymentMethodAr = $paymentMethodDisplay;

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
            margin: 0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: #f3f4f6;
            color: #000000;
            margin: 0;
            padding: 0;
            font-size: 12px;
            line-height: 1.4;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
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
            width: 100%;
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

        /* Invoice Outer Viewport Container (Zero horizontal overflow) */
        .invoice-viewport-container {
            width: 100%;
            max-width: 100vw;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            overflow-x: hidden;
            padding: 0;
            box-sizing: border-box;
        }

        /* Responsive scale box that shrinks to exact screen width on mobile */
        .invoice-scale-box {
            width: 820px;
            margin: 20px auto 40px auto;
            position: relative;
        }

        /* Invoice Paper Container (A4 Proportions: exactly 820px base desktop width) */
        .invoice-wrapper {
            width: 820px;
            min-width: 820px;
            max-width: 820px;
            background: #ffffff;
            padding: 30px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
            box-sizing: border-box;
            transform-origin: top left;
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
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .toolbar-wrapper {
                display: none !important;
            }

            .invoice-viewport-container {
                display: block !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
            }

            .invoice-scale-box {
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                margin: 0 !important;
                position: static !important;
            }

            .invoice-wrapper {
                box-shadow: none !important;
                border-radius: 0 !important;
                border: none !important;
                margin: 0 auto !important;
                padding: 10mm 12mm 12mm 12mm !important;
                max-width: 100% !important;
                min-width: 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
                position: static !important;
                transform: none !important;
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

    <!-- Printable Invoice Page in Viewport Container (Auto-scales on Mobile to preserve desktop look) -->
    <div class="invoice-viewport-container">
        <div class="invoice-scale-box">
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
                    @php
                        $recFullName = trim((string)($order->recipient_name ?: trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''))));
                        $recParts = explode(' ', $recFullName, 2);
                        $recFirst = $recParts[0] ?? $recFullName;
                        $recLast = $recParts[1] ?? '';
                    @endphp
                    <div class="meta-row"><strong>First name:</strong> {{ $recFirst }}</div>
                    @if(!empty($recLast))
                    <div class="meta-row"><strong>Last name:</strong> {{ $recLast }}</div>
                    @endif
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
                        
                        // Robust Product Image URL Resolution
                        $imgCandidate = $item->product_image ?: ($prod?->image ?: $prod?->thumbnail_image);
                        $thumb = null;
                        if (!empty($imgCandidate)) {
                            if (is_array($imgCandidate)) {
                                $imgCandidate = reset($imgCandidate);
                            }
                            $thumb = function_exists('storage_media_url') ? storage_media_url($imgCandidate) : asset('storage/' . ltrim((string)$imgCandidate, '/'));
                        }
                        if (empty($thumb) && !empty($prod?->image_url)) {
                            $thumb = $prod->image_url;
                        }
                        
                        // Prioritize Actual Product Name/Code (e.g. G017) over numeric WooCommerce SKU (e.g. 88)
                        $rawProdName = $item->product_name ?: ($prod ? $prod->name : null);
                        if (is_array($rawProdName)) {
                            $prodTitle = $rawProdName['en'] ?? $rawProdName['ar'] ?? reset($rawProdName);
                        } else {
                            $prodTitle = (string)$rawProdName;
                        }

                        $prodDisplay = !empty($prodTitle) ? $prodTitle : ($prod?->sku ?: 'G408');
                        $skuDisplay = (!empty($prod?->sku) && (string)$prod->sku !== (string)$prodDisplay) ? $prod->sku : null;

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
                                <img src="{{ $thumb }}" class="prod-thumb" alt="Product" onerror="this.onerror=null; this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-flex';">
                                <div style="display: none; width: 48px; height: 48px; background: #f3f4f6; border-radius: 4px; align-items: center; justify-content: center; font-size: 10px; color: #9ca3af; border: 1px solid #d1d5db;">Flower</div>
                            @else
                                <div style="width: 48px; height: 48px; background: #f3f4f6; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; color: #9ca3af; border: 1px solid #d1d5db;">Flower</div>
                            @endif
                        </td>
                        <td>
                            <div class="prod-code">{{ $prodDisplay }}</div>
                            @if($skuDisplay)
                                <div style="font-size: 11px; color: #6b7280; font-weight: normal;">SKU: {{ $skuDisplay }}</div>
                            @endif
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

            $shippingRaw = strtolower((string)($order->shipping_method ?: ($order->shipping_method_name ?: '')));
            if (str_contains($shippingRaw, 'jeddah') || empty($shippingRaw)) {
                $shippingLabelEn = 'Shipping (Delivery inside Jeddah)';
                $shippingLabelAr = 'الشحن (توصيل داخل جدة)';
            } elseif (str_contains($shippingRaw, 'pickup') || str_contains($shippingRaw, 'استلام')) {
                $shippingLabelEn = 'Store Pickup';
                $shippingLabelAr = 'الاستلام من الفرع';
            } else {
                $shippingLabelEn = 'Shipping (' . ucwords(str_replace(['_', '-'], ' ', $order->shipping_method ?: 'Standard Delivery')) . ')';
                $shippingLabelAr = 'الشحن (' . ($order->shipping_method ?: 'توصيل قياسي') . ')';
            }
        @endphp
        <table class="totals-table">
            <tr>
                <td class="tot-en">Payment method</td>
                <td class="tot-ar">طريقة الدفع</td>
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
                <td class="tot-en">{{ $shippingLabelEn }}</td>
                <td class="tot-ar">{{ $shippingLabelAr }}</td>
                <td class="tot-val"><span class="sar-symbol">﷼</span>{{ $fmtShipping }}</td>
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
                <td class="tot-val" style="color: #15803d; font-size: 13px;">${{ number_format((float)$order->currency_amount, 2) }} USD</td>
            </tr>
            @endif
        </table>

    </div>
    </div>
    </div>

    <!-- Mobile View Auto-Fit Scaling Script (Eliminates phantom horizontal scroll) -->
    <script>
        function autoScaleInvoice() {
            var wrapper = document.querySelector('.invoice-wrapper');
            var scaleBox = document.querySelector('.invoice-scale-box');
            if (!wrapper || !scaleBox) return;

            var screenWidth = window.innerWidth || document.documentElement.clientWidth;
            var targetWidth = 820;

            if (screenWidth < targetWidth) {
                var padding = screenWidth < 400 ? 12 : 20;
                var availableWidth = screenWidth - padding;
                var scale = availableWidth / targetWidth;
                var scaledWidth = Math.round(targetWidth * scale);

                // Temporarily reset to measure natural unscaled height
                wrapper.style.transform = 'none';
                wrapper.style.position = 'static';
                var naturalHeight = wrapper.offsetHeight;

                // Scale the wrapper inside the clipped scale box
                wrapper.style.transformOrigin = 'top left';
                wrapper.style.transform = 'scale(' + scale + ')';
                wrapper.style.position = 'absolute';
                wrapper.style.top = '0';
                wrapper.style.left = '0';
                wrapper.style.margin = '0';

                // Scale box takes EXACT scaled width & height so NO extra scrollbar can exist!
                var scaledHeight = Math.round(naturalHeight * scale);
                scaleBox.style.width = scaledWidth + 'px';
                scaleBox.style.height = scaledHeight + 'px';
                scaleBox.style.overflow = 'hidden';
                scaleBox.style.margin = '15px auto 40px auto';
            } else {
                wrapper.style.transform = 'none';
                wrapper.style.position = 'static';
                wrapper.style.margin = '0';
                scaleBox.style.width = targetWidth + 'px';
                scaleBox.style.height = 'auto';
                scaleBox.style.overflow = 'visible';
                scaleBox.style.margin = '20px auto 40px auto';
            }
        }

        window.addEventListener('resize', autoScaleInvoice);
        window.addEventListener('orientationchange', autoScaleInvoice);
        window.addEventListener('DOMContentLoaded', autoScaleInvoice);
        window.addEventListener('load', autoScaleInvoice);
        setTimeout(autoScaleInvoice, 30);
        setTimeout(autoScaleInvoice, 300);
    </script>

    @if(request('print') == 1 || request('download') == 1)
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.print();
            }, 600);
        });
    </script>
    @endif
</body>
</html>
