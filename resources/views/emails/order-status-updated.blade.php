<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Update - #{{ $order->order_number }}</title>
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

        /* Items Table */
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

        /* Order Totals Table */
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
            <h1 style="color: #1e293b; font-size: 20px; margin-top: 0; margin-bottom: 10px;">Hi {{ $order->first_name ?? $order->customer_name ?? 'Customer' }},</h1>
            
            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569;">Your order status has been updated. Here are the details of your order:</p>

            <!-- Dynamic Status Banner -->
            @php
                $statusKey = strtolower(trim($status));

                // Determine box background & border based on status
                $boxStyles = match($statusKey) {
                    'cancelled', 'declined' => 'background-color: #fef2f2; border: 1px solid #fecaca;',
                    'completed', 'delivered' => 'background-color: #f0fdf4; border: 1px solid #bbf7d0;',
                    'order_shipped', 'shipped', 'processing' => 'background-color: #eff6ff; border: 1px solid #bfdbfe;',
                    'refunded' => 'background-color: #faf5ff; border: 1px solid #e9d5ff;',
                    default => 'background-color: #fffbeb; border: 1px solid #fde68a;'
                };

                // Determine badge background color
                $badgeBg = match($statusKey) {
                    'cancelled', 'declined' => '#dc2626',
                    'completed', 'delivered' => '#16a34a',
                    'order_shipped', 'shipped', 'processing' => '#2563eb',
                    'refunded' => '#7e22ce',
                    default => '#d97706'
                };

                // Base image URL resolution
                $mediaBaseUrl = rtrim($appUrl ?: config('app.url', 'https://grassflorist.com'), '/');
            @endphp

            <div style="{{ $boxStyles }} border-radius: 8px; padding: 16px 20px; margin: 18px 0; display: block;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td valign="middle">
                            <span style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Current Status</span><br>
                            <span style="background-color: {{ $badgeBg }}; color: #ffffff; padding: 4px 14px; border-radius: 12px; font-weight: bold; font-size: 12px; text-transform: uppercase; display: inline-block; margin-top: 4px; letter-spacing: 0.5px;">
                                {{ str_replace('_', ' ', ucfirst($status)) }}
                            </span>
                        </td>
                        <td align="right" valign="middle" style="font-size: 13px; color: #475569;">
                            <strong style="color: #0f172a; font-size: 14px;">Order #{{ $order->order_number }}</strong><br>
                            <span style="font-size: 12px; color: #64748b;">{{ $order->created_at ? $order->created_at->format('d M, Y') : now()->format('d M, Y') }}</span>
                        </td>
                    </tr>

                    @if($statusKey === 'order_shipped' && (!empty($order->tracking_id) || !empty($order->courier_partner)))
                    <tr>
                        <td colspan="2" style="padding-top: 12px;">
                            <div style="background-color: #ffffff; border: 1px solid #bfdbfe; border-left: 4px solid #2563eb; border-radius: 6px; padding: 10px 14px;">
                                @if(!empty($order->courier_partner))
                                    <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; color: #1e40af; letter-spacing: 0.5px;">Courier Partner</div>
                                    <div style="font-size: 13px; color: #1e3a8a; font-weight: 600; margin-top: 2px; margin-bottom: {{ !empty($order->tracking_id) ? '8px' : '0' }};">{{ $order->courier_partner }}</div>
                                @endif
                                @if(!empty($order->tracking_id))
                                    <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; color: #1e40af; letter-spacing: 0.5px;">Tracking / Consignment ID</div>
                                    <div style="font-size: 13px; color: #1e3a8a; font-family: monospace; font-weight: bold; margin-top: 2px;">{{ $order->tracking_id }}</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endif

                    @if($statusKey === 'refunded')
                    <tr>
                        <td colspan="2" style="padding-top: 12px;">
                            <div style="background-color: #ffffff; border: 1px solid #e9d5ff; border-left: 4px solid #7e22ce; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; color: #6b21a8; letter-spacing: 0.5px;">Refund Processed</div>
                                <div style="font-size: 13px; color: #581c87; font-weight: 500; margin-top: 3px; line-height: 1.4;">The refund for this order has been processed to your original payment source / method.</div>
                            </div>
                        </td>
                    </tr>
                    @endif

                    @if(in_array($statusKey, ['cancelled', 'declined']) && !empty($order->cancellation_reason))
                    <tr>
                        <td colspan="2" style="padding-top: 12px;">
                            <div style="background-color: #ffffff; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 6px; padding: 10px 14px;">
                                <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; color: #991b1b; letter-spacing: 0.5px;">Reason for Cancellation</div>
                                <div style="font-size: 13px; color: #7f1d1d; font-weight: 500; margin-top: 3px; line-height: 1.4;">{{ $order->cancellation_reason }}</div>
                            </div>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>

            <!-- Product Items Table -->
            <p style="font-weight: bold; color: #1e293b; margin-top: 24px; margin-bottom: 8px; font-size: 14px;">Order Items:</p>
            <table class="items-table" cellpadding="0" cellspacing="0" width="100%" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="background-color: #f8fafc;">
                        <th width="70" style="padding: 10px 12px; text-align: center; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">Image</th>
                        <th style="padding: 10px 12px; text-align: left; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">Product Details</th>
                        <th width="90" style="padding: 10px 12px; text-align: right; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($orderItems) && count($orderItems) > 0)
                        @foreach($orderItems as $item)
                            @php
                                $rawImage = !empty($item->product_image) ? $item->product_image : ($item->product?->image ?? null);
                                $imgSrc = null;

                                if (!empty($rawImage)) {
                                    if (str_starts_with($rawImage, 'http://') || str_starts_with($rawImage, 'https://')) {
                                        $imgSrc = $rawImage;
                                    } else {
                                        $cleanPath = ltrim($rawImage, '/');
                                        // Remove duplicate storage prefixes if already stored
                                        $cleanPath = preg_replace('#^(storage/app/public/|app/public/|storage/)#', '', $cleanPath);
                                        $imgSrc = $mediaBaseUrl . '/storage/app/public/' . $cleanPath;
                                    }
                                }
                            @endphp
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td align="center" style="padding: 12px; vertical-align: middle;">
                                    @if(!empty($imgSrc))
                                        <img src="{{ $imgSrc }}" alt="{{ $item->product_name ?? 'Book' }}" width="55" height="55" class="product-img" style="width: 55px; height: 55px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; display: block; margin: 0 auto;">
                                    @else
                                        <div style="width: 55px; height: 55px; background-color: #f1f5f9; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center; line-height: 55px; font-size: 22px; margin: 0 auto;">📖</div>
                                    @endif
                                </td>
                                <td style="padding: 12px; vertical-align: middle;">
                                    <div class="product-name" style="font-weight: bold; color: #1e293b; font-size: 13px; line-height: 1.4; margin-bottom: 4px;">{{ $item->product_name ?? ($item->product->name ?? 'Book / Product') }}</div>
                                    <div class="product-qty" style="color: #64748b; font-size: 12px;">Qty: {{ $item->quantity }} &times; ₹{{ number_format($item->price, 2) }}</div>
                                    @if(!empty($item->courier_name) || !empty($item->tracking_number))
                                        <div style="margin-top: 4px; font-size: 11px; color: #2563eb;">
                                            <strong>Tracking:</strong> {{ $item->courier_name ?? 'Courier' }} - {{ $item->tracking_number }}
                                        </div>
                                    @endif
                                </td>
                                <td align="right" style="padding: 12px; vertical-align: middle; font-weight: bold; color: #1e293b; font-size: 13px;">
                                    ₹{{ number_format($item->price * $item->quantity, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>

            <!-- Order Financial Totals -->
            <table class="totals-table" cellpadding="0" cellspacing="0" width="100%" style="width: 100%; margin-top: 15px; border-collapse: collapse;">
                <tr>
                    <td class="totals-label" style="text-align: right; color: #64748b; padding: 4px 8px; font-size: 13px; width: 70%;">Subtotal:</td>
                    <td class="totals-value" style="text-align: right; font-weight: 600; color: #1e293b; padding: 4px 8px; font-size: 13px; width: 30%;">₹{{ number_format($order->subtotal ?? 0, 2) }}</td>
                </tr>
                @if(($order->discount_amount ?? $order->discount ?? 0) > 0)
                <tr>
                    <td class="totals-label" style="text-align: right; color: #64748b; padding: 4px 8px; font-size: 13px;">Discount:</td>
                    <td class="totals-value" style="text-align: right; font-weight: 600; color: #16a34a; padding: 4px 8px; font-size: 13px;">-₹{{ number_format($order->discount_amount ?? $order->discount, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="totals-label" style="text-align: right; color: #64748b; padding: 4px 8px; font-size: 13px;">Shipping:</td>
                    <td class="totals-value" style="text-align: right; font-weight: 600; color: #1e293b; padding: 4px 8px; font-size: 13px;">₹{{ number_format($order->shipping_amount ?? 0, 2) }}</td>
                </tr>
                @if(strtolower($order->payment_method ?? '') === 'cod' || ($order->delivery_amount ?? 0) > 0)
                <tr>
                    <td class="totals-label" style="text-align: right; color: #64748b; padding: 4px 8px; font-size: 13px;">COD Charges:</td>
                    <td class="totals-value" style="text-align: right; font-weight: 600; color: #1e293b; padding: 4px 8px; font-size: 13px;">₹{{ number_format($order->delivery_amount ?? 0, 2) }}</td>
                </tr>
                @endif
                <tr class="grand-total" style="border-top: 2px solid #e2e8f0;">
                    <td class="totals-label" style="text-align: right; font-weight: bold; color: #0f172a; padding: 8px; font-size: 15px;">Grand Total:</td>
                    <td class="totals-value" style="text-align: right; font-weight: bold; color: #0f172a; padding: 8px; font-size: 15px;">{{ number_format($order->total_amount ?? 0, 2) }} {{ $order->currency ?: 'SAR' }}</td>
                </tr>
            </table>

            <p style="margin-top: 26px; font-size: 13px; color: #64748b; line-height: 1.5;">If you have any questions about your order, please reply to this email or contact us at <a href="mailto:info@grassflorist.com" style="color: #8fae2a; text-decoration: none; font-weight: 600;">info@grassflorist.com</a>.</p>
            
            <p style="font-size: 13px; color: #475569; margin-bottom: 0;">Best regards,<br><strong style="color: #0f172a;">The {{ config('app.name') }} Team</strong></p>
        </div>
        
        <!-- Footer -->
        <div class="footer" style="text-align: center; padding: 18px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; background-color: #f8fafc;">
            <p style="margin: 0;">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
