<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mailSubject ?? 'Complete Your Order - ' . config('app.name') }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 20px;
            background-color: #f4f6f9;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .header {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .logo {
            max-width: 90px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .content {
            padding: 24px;
        }
        h1 {
            color: #1e293b;
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 12px;
        }
        p {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #475569;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            background-color: #f8fafc;
            padding: 10px 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 13px;
        }
        .product-img {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            display: block;
            margin: 0 auto;
        }
        .product-name {
            font-weight: bold;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 4px;
        }
        .product-qty {
            color: #64748b;
            font-size: 12px;
        }
        .totals-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 8px;
            font-size: 13px;
        }
        .totals-label {
            text-align: right;
            color: #64748b;
            width: 70%;
        }
        .totals-value {
            text-align: right;
            font-weight: 600;
            color: #1e293b;
            width: 30%;
        }
        .grand-total td {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            border-top: 2px solid #e2e8f0;
            padding-top: 10px;
        }
        .footer {
            text-align: center;
            padding: 18px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
            background-color: #f8fafc;
        }
        .footer a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>
<body>
    @php
        $mediaBaseUrl = rtrim(config('app.url', 'https://grassflorist.com'), '/');

        $customerDisplayName = $cart->getEffectiveCustomerName();
        $totalItems = $cart->getItemsCount();
        $cartTotal = $cart->calculateTotal();
        $supportEmail = config('app.admin_email') ?: env('ADMIN_EMAIL', 'info@grassflorist.com');
        $logoPath = public_path('images/grass-logo.webp');
        $brandLogo = file_exists($logoPath)
            ? ('data:image/webp;base64,' . base64_encode(file_get_contents($logoPath)))
            : asset('images/grass-logo.webp');
    @endphp

    <div class="email-container" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; max-width: 600px; margin: 0 auto; border: 1px solid #EFE7DC; font-family: 'Segoe UI', Roboto, sans-serif;">
        <!-- Logo Header -->
        <div class="header" style="background-color: #2D3F33; padding: 24px; text-align: center; border-bottom: 3px solid #8fae2a;">
            <img src="{{ $brandLogo }}" alt="Grass Florist" class="logo" style="max-width: 130px; height: auto; display: block; margin: 0 auto; filter: brightness(0) invert(1);">
            <div style="color: #C2D4BE; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin-top: 6px;">Luxury Floral Atelier</div>
        </div>
        
        <!-- Content -->
        <div class="content" style="padding: 24px;">
            <h1 style="color: #1e293b; font-size: 20px; margin-top: 0; margin-bottom: 10px;">Hi {{ $customerDisplayName }},</h1>
            
            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569;">
                You left some items in your shopping bag at <strong>{{ config('app.name') }}</strong>. We have saved them for you so you can easily complete your purchase.
            </p>

            @if(!empty($messageBody) && $messageBody !== $cart->getReminderMessageText())
                <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 12px 16px; margin: 16px 0; border-radius: 4px; font-size: 13px; color: #1e3a8a; line-height: 1.5;">
                    {!! nl2br(e($messageBody)) !!}
                </div>
            @endif

            <!-- Cart Meta Banner -->
            <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 18px; margin: 16px 0;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td valign="middle">
                            <span style="font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px;">Shopping Bag Reminder</span><br>
                            <span style="font-size: 15px; font-weight: 800; color: #0f172a;">{{ $totalItems }} {{ $totalItems === 1 ? 'Book' : 'Books' }} Waiting</span>
                        </td>
                        <td align="right" valign="middle" style="font-size: 12px; color: #64748b;">
                            <strong>Total:</strong> ₹{{ number_format($cartTotal, 2) }}<br>
                            <span style="color: #16a34a; font-weight: 600;">Stock Reserved</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Product Items Table -->
            <p style="font-weight: bold; color: #1e293b; margin-top: 22px; margin-bottom: 8px; font-size: 14px;">Items in Your Bag:</p>
            <table class="items-table" cellpadding="0" cellspacing="0" width="100%" style="width: 100%; border-collapse: collapse; margin-bottom: 18px;">
                <thead>
                    <tr style="background-color: #f8fafc;">
                        <th width="70" style="padding: 10px 12px; text-align: center; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">Image</th>
                        <th style="padding: 10px 12px; text-align: left; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">Product Details</th>
                        <th width="90" style="padding: 10px 12px; text-align: right; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cart->items as $item)
                        @php
                            $rawImage = !empty($item->image) ? $item->image : ($item->product?->image ?? null);
                            $imgSrc = null;

                            if (!empty($rawImage)) {
                                if (str_starts_with($rawImage, 'http://') || str_starts_with($rawImage, 'https://')) {
                                    $imgSrc = $rawImage;
                                } else {
                                    $cleanPath = ltrim($rawImage, '/');
                                    $cleanPath = preg_replace('#^(storage/app/public/|app/public/|storage/)#', '', $cleanPath);
                                    $imgSrc = $mediaBaseUrl . '/storage/app/public/' . $cleanPath;
                                }
                            }

                            $itemPrice = (float) ($item->price ?? ($item->product?->price ?? 0));
                            $itemQty = (int) $item->quantity;
                            $subtotal = $itemPrice * $itemQty;
                        @endphp
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td align="center" style="padding: 12px; vertical-align: middle;">
                                @if(!empty($imgSrc))
                                    <img src="{{ $imgSrc }}" alt="{{ $item->product?->name ?? 'Book' }}" width="55" height="55" class="product-img" style="width: 55px; height: 55px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; display: block; margin: 0 auto;">
                                @else
                                    <div style="width: 55px; height: 55px; background-color: #f1f5f9; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center; line-height: 55px; font-size: 22px; margin: 0 auto;">📖</div>
                                @endif
                            </td>
                            <td style="padding: 12px; vertical-align: middle;">
                                <div class="product-name" style="font-weight: bold; color: #1e293b; font-size: 13px; line-height: 1.4; margin-bottom: 4px;">{{ $item->product?->name ?? 'Book / Product' }}</div>
                                <div class="product-qty" style="color: #64748b; font-size: 12px;">Qty: {{ $itemQty }} &times; ₹{{ number_format($itemPrice, 2) }}</div>
                            </td>
                            <td align="right" style="padding: 12px; vertical-align: middle; font-weight: bold; color: #1e293b; font-size: 13px;">
                                ₹{{ number_format($subtotal, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Financial Totals -->
            <table class="totals-table" cellpadding="0" cellspacing="0" width="100%" style="width: 100%; margin-top: 15px; border-collapse: collapse;">
                <tr>
                    <td class="totals-label" style="text-align: right; color: #64748b; padding: 4px 8px; font-size: 13px; width: 70%;">Total Quantity:</td>
                    <td class="totals-value" style="text-align: right; font-weight: 600; color: #1e293b; padding: 4px 8px; font-size: 13px; width: 30%;">{{ $totalItems }} {{ $totalItems === 1 ? 'item' : 'items' }}</td>
                </tr>
                <tr class="grand-total" style="border-top: 2px solid #e2e8f0;">
                    <td class="totals-label" style="text-align: right; font-weight: bold; color: #0f172a; padding: 8px; font-size: 15px;">Estimated Total:</td>
                    <td class="totals-value" style="text-align: right; font-weight: bold; color: #0f172a; padding: 8px; font-size: 15px;">₹{{ number_format($cartTotal, 2) }}</td>
                </tr>
            </table>

            <!-- Direct 1-Click Recovery CTA Button -->
            <div style="text-align: center; margin: 30px 0 16px 0;">
                <a href="{{ $recoveryUrl }}" style="background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);">Complete Your Order &rarr;</a>
            </div>
            <p style="font-size: 12px; color: #94a3b8; text-align: center; margin: 0 0 24px 0;">
                Or open this link: <a href="{{ $recoveryUrl }}" style="color: #2563eb; text-decoration: underline; word-break: break-all;">{{ $recoveryUrl }}</a>
            </p>

            <p style="margin-top: 24px; font-size: 13px; color: #64748b; line-height: 1.5;">
                If you have any questions or need help with your order, please reply to this email or contact us at <a href="mailto:{{ $supportEmail }}" style="color: #2563eb; text-decoration: none; font-weight: 600;">{{ $supportEmail }}</a>.
            </p>
            
            <p style="font-size: 13px; color: #475569; margin-bottom: 0;">
                Best regards,<br><strong style="color: #0f172a;">The {{ config('app.name') }} Team</strong>
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer" style="text-align: center; padding: 18px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; background-color: #f8fafc;">
            <p style="margin: 0;">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
