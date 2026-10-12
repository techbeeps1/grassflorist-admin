<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Order Received - #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f7f6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 650px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 6px 0;
            font-size: 20px;
            font-weight: 600;
        }
        .header p {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .content {
            padding: 24px;
        }
        .alert-box {
            background-color: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-box p {
            margin: 0;
            font-size: 14px;
            color: #065f46;
        }
        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin: 20px 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 2px solid #f1f5f9;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 16px;
        }
        .info-grid td {
            padding: 4px 0;
            font-size: 13px;
            vertical-align: top;
        }
        .info-grid .label {
            font-weight: 600;
            color: #64748b;
            width: 140px;
        }
        .info-grid .value {
            color: #0f172a;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 16px;
        }
        table.items-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 10px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        table.items-table td {
            padding: 10px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            color: #334155;
        }
        .financial-summary {
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            margin-top: 16px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 13px;
        }
        .summary-row.total {
            border-top: 1px solid #cbd5e1;
            margin-top: 8px;
            padding-top: 8px;
            font-size: 15px;
            font-weight: 700;
            color: #059669;
        }
        .btn-container {
            text-align: center;
            margin: 28px 0 16px 0;
        }
        .btn {
            display: inline-block;
            background-color: #059669;
            color: #ffffff !important;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
        }
        .footer {
            background-color: #f8fafc;
            text-align: center;
            padding: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Grass Florist Atelier</h1>
        <p>New Order Notification for {{ $vendor->vendor_name ?? 'Your Store' }}</p>
    </div>

    <div class="content">
        <div class="alert-box">
            <p><strong>Congratulations!</strong> You have received a new customer order for your products.</p>
        </div>

        <div class="section-title">Order Information</div>
        <table class="info-grid">
            <tr>
                <td class="label">Order Number:</td>
                <td class="value"><strong>#{{ $order->order_number }}</strong></td>
            </tr>
            <tr>
                <td class="label">Order Date:</td>
                <td class="value">{{ $order->created_at ? $order->created_at->format('d M, Y h:i A') : now()->format('d M, Y') }}</td>
            </tr>
            <tr>
                <td class="label">Payment Method:</td>
                <td class="value">{{ strtoupper($order->payment_method ?? 'N/A') }} ({{ ucfirst($order->payment_status ?? 'Pending') }})</td>
            </tr>
        </table>

        <div class="section-title">Products Ordered From Your Store</div>
        @php
            $mediaBaseUrl = rtrim(config('app.url', 'https://grassflorist.com'), '/');
        @endphp
        <table class="items-table">
            <thead>
                <tr>
                    <th width="40" style="text-align: center;">#</th>
                    <th width="50" style="text-align: center;">Image</th>
                    <th>Product</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vendorItems as $index => $item)
                @php
                    $productName = $item->product ? $item->product->name : ($item->product_name ?? 'Product #' . $item->product_id);
                    $subtotal = ($item->quantity ?? 1) * ($item->price ?? 0);
                    $rawImage = !empty($item->product_image) ? $item->product_image : ($item->product?->image ?? null);
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
                @endphp
                <tr>
                    <td style="text-align: center; vertical-align: middle;">{{ $loop->iteration }}</td>
                    <td style="text-align: center; vertical-align: middle;">
                        @if(!empty($imgSrc))
                            <img src="{{ $imgSrc }}" alt="{{ $productName }}" width="45" height="45" style="width: 45px; height: 45px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; display: block; margin: 0 auto;">
                        @else
                            <div style="width: 45px; height: 45px; background-color: #f1f5f9; border-radius: 4px; border: 1px solid #e2e8f0; text-align: center; line-height: 45px; font-size: 18px; margin: 0 auto;">📖</div>
                        @endif
                    </td>
                    <td style="vertical-align: middle;"><strong>{{ $productName }}</strong></td>
                    <td style="text-align: center; vertical-align: middle;">{{ $item->quantity ?? 1 }}</td>
                    <td style="text-align: right; vertical-align: middle;">₹{{ number_format($item->price ?? 0, 2) }}</td>
                    <td style="text-align: right; font-weight: 600; vertical-align: middle;">₹{{ number_format($subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Shipping & Delivery Details</div>
        <table class="info-grid">
            <tr>
                <td class="label">Customer Name:</td>
                <td class="value">{{ $order->first_name }} {{ $order->last_name }}</td>
            </tr>
            <tr>
                <td class="label">Delivery Address:</td>
                <td class="value">{{ $order->address }}</td>
            </tr>
            <tr>
                <td class="label">City / State / PIN:</td>
                <td class="value">{{ $order->city }}, {{ $order->state }} - {{ $order->zip_code }}</td>
            </tr>
            <tr>
                <td class="label">Contact Phone:</td>
                <td class="value">{{ $order->formatted_phone ?? $order->customer_phone ?? 'N/A' }}</td>
            </tr>
        </table>

        <div class="financial-summary">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="padding: 3px 0; font-size: 13px; color: #64748b;">Your Items Gross Total:</td>
                    <td style="padding: 3px 0; font-size: 13px; text-align: right; font-weight: 600;">₹{{ number_format($grossAmount, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; font-size: 13px; color: #d97706;">Platform Commission Fee ({{ $commissionRate }}%):</td>
                    <td style="padding: 3px 0; font-size: 13px; text-align: right; font-weight: 600; color: #d97706;">-₹{{ number_format($commissionFee, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; font-size: 13px; color: #d97706;">GST on Platform Fee ({{ $commissionGstRate }}%):</td>
                    <td style="padding: 3px 0; font-size: 13px; text-align: right; font-weight: 600; color: #d97706;">-₹{{ number_format($commissionGst, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; font-size: 13px; color: #b91c1c; font-weight: 600;">Total Platform Charges:</td>
                    <td style="padding: 3px 0; font-size: 13px; text-align: right; font-weight: 700; color: #b91c1c;">-₹{{ number_format($totalDeduction, 2) }}</td>
                </tr>
                <tr style="border-top: 1px solid #cbd5e1;">
                    <td style="padding: 8px 0 0 0; font-size: 15px; font-weight: 700; color: #059669;">Your Net Payable Payout:</td>
                    <td style="padding: 8px 0 0 0; font-size: 16px; text-align: right; font-weight: 700; color: #059669;">₹{{ number_format($netPayout, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="btn-container">
            <a href="{{ url('/admin/orders/' . $order->id . '/edit') }}" class="btn">View & Fulfill Order in Portal</a>
        </div>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} Grass Florist Atelier. All rights reserved.</p>
        <p>This is an automated notification sent to verified merchants on Grass Florist.</p>
    </div>
</div>

</body>
</html>
