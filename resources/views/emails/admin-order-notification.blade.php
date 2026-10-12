<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Order Notification - #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #1E1915;
            margin: 0;
            padding: 24px;
            background-color: #FAF8F5;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #EFE7DC;
            box-shadow: 0 4px 16px rgba(45, 63, 51, 0.05);
        }
        .header {
            background-color: #2D3F33;
            padding: 26px 20px;
            text-align: center;
            border-bottom: 3px solid #8fae2a;
        }
        .logo {
            max-width: 130px;
            height: auto;
            display: block;
            margin: 0 auto;
            filter: brightness(0) invert(1);
        }
        .subtitle {
            color: #C2D4BE;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 6px;
        }
        .content {
            padding: 28px 24px;
        }
        h1 {
            color: #2D3F33;
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .card {
            background-color: #FAF8F5;
            border: 1px solid #EAE3D7;
            border-radius: 10px;
            padding: 18px;
            margin: 18px 0;
        }
        .row {
            margin-bottom: 10px;
            font-size: 14px;
        }
        .row strong {
            color: #2D3F33;
            display: inline-block;
            width: 140px;
        }
        .button {
            display: inline-block;
            background-color: #2D3F33;
            color: #ffffff !important;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
        }
        .footer {
            text-align: center;
            padding: 18px;
            border-top: 1px solid #EFE7DC;
            font-size: 12px;
            color: #8C827A;
            background-color: #FAF8F5;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/grass-logo.webp');
        $brandLogo = file_exists($logoPath)
            ? ('data:image/webp;base64,' . base64_encode(file_get_contents($logoPath)))
            : asset('images/grass-logo.webp');
        $currency = $order->currency ?: 'SAR';
    @endphp

    <div class="email-container">
        <div class="header">
            <img src="{{ $brandLogo }}" alt="Grass Florist" class="logo">
            <div class="subtitle">Admin Notification &bull; Store Dispatch</div>
        </div>
        
        <div class="content">
            <h1>New Customer Order Received</h1>
            <p style="color: #5A5049; margin-bottom: 18px;">
                A new order has been placed on the Grass Florist store. Details are provided below:
            </p>

            <div class="card">
                <div class="row">
                    <strong>Order Number:</strong>
                    <span style="font-weight: bold; color: #2D3F33;">#{{ $order->order_number ?: $order->id }}</span>
                </div>
                <div class="row">
                    <strong>Customer:</strong>
                    <span>{{ $order->user ? $order->user->name : ($order->name ?? $order->email) }}</span>
                </div>
                <div class="row">
                    <strong>Email:</strong>
                    <span><a href="mailto:{{ $order->email }}" style="color: #8fae2a;">{{ $order->email }}</a></span>
                </div>
                @if(!empty($order->phone))
                <div class="row">
                    <strong>Phone:</strong>
                    <span>{{ $order->phone }}</span>
                </div>
                @endif
                <div class="row">
                    <strong>Order Total:</strong>
                    <span style="font-weight: bold; color: #2D3F33;">{{ number_format((float)$order->total_amount, 2) }} {{ $currency }}</span>
                </div>
                <div class="row">
                    <strong>Payment Method:</strong>
                    <span>{{ strtoupper($order->payment_method ?? 'Online') }}</span>
                </div>
                @if(!empty($order->delivery_date))
                <div class="row">
                    <strong>Delivery Date:</strong>
                    <span>{{ $order->delivery_date }} ({{ $order->delivery_time ?? 'Standard' }})</span>
                </div>
                @endif
                @if(!empty($order->recipient_name))
                <div class="row">
                    <strong>Recipient:</strong>
                    <span>{{ $order->recipient_name }} ({{ $order->recipient_phone ?? 'N/A' }})</span>
                </div>
                @endif
            </div>

            @php
                $orderKey = $order->order_key;
                $orderIdentifier = $order->order_number ?: $order->id;
                $invoiceUrl = config('app.url') . "/orders/{$orderIdentifier}/invoice?key={$orderKey}";
            @endphp
            <div style="text-align: center; margin-top: 24px;">
                <a href="{{ $invoiceUrl }}" class="button">View Tax Invoice & Dispatch Order</a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Grass Florist Atelier. All rights reserved.</p>
            <p>Jeddah, Kingdom of Saudi Arabia</p>
        </div>
    </div>
</body>
</html>