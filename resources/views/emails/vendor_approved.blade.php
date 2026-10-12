<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vendor Account Approved - {{ $vendor->vendor_name }}</title>
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
            padding: 26px 24px;
            text-align: center;
        }
        .header img {
            max-width: 140px;
            max-height: 50px;
            margin-bottom: 12px;
            display: inline-block;
        }
        .header h1 {
            margin: 0 0 6px 0;
            font-size: 21px;
            font-weight: 600;
            color: #ffffff;
        }
        .header p {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .content {
            padding: 24px;
        }
        .celebration-box {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #10b981;
            padding: 16px 20px;
            margin-bottom: 22px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-badge {
            display: inline-block;
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            padding: 4px 12px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
        }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin: 22px 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 2px solid #f1f5f9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .info-grid td {
            padding: 6px 0;
            font-size: 13px;
            vertical-align: top;
            border-bottom: 1px solid #f8fafc;
        }
        .info-grid .label {
            font-weight: 600;
            color: #64748b;
            width: 160px;
        }
        .info-grid .value {
            color: #0f172a;
            font-weight: 500;
        }
        .steps-container {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 16px 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .steps-list {
            margin: 0;
            padding-left: 20px;
            color: #334155;
            font-size: 13px;
        }
        .steps-list li {
            margin-bottom: 8px;
        }
        .btn-container {
            text-align: center;
            margin: 28px 0 16px 0;
        }
        .btn {
            display: inline-block;
            background-color: #16a34a;
            color: #ffffff !important;
            padding: 13px 32px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(22, 163, 74, 0.25);
        }
        .btn:hover {
            background-color: #15803d;
        }
        .footer {
            background-color: #f8fafc;
            text-align: center;
            padding: 18px 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
        }
        .footer p {
            margin: 4px 0;
        }
        .footer a {
            color: #0284c7;
            text-decoration: none;
        }
    </style>
</head>
    @php
        $logoPath = public_path('images/grass-logo.webp');
        $brandLogo = file_exists($logoPath)
            ? ('data:image/webp;base64,' . base64_encode(file_get_contents($logoPath)))
            : asset('images/grass-logo.webp');
    @endphp
<div class="container" style="border: 1px solid #EFE7DC; border-radius: 12px; overflow: hidden; background: #ffffff;">
    <div class="header" style="background-color: #2D3F33; border-bottom: 3px solid #8fae2a; padding: 24px; text-align: center;">
        <img src="{{ $brandLogo }}" alt="Grass Florist Logo" style="max-width: 130px; height: auto; display: block; margin: 0 auto; filter: brightness(0) invert(1);">
        <h1 style="color: #ffffff; font-size: 20px; margin-top: 10px;">Welcome to Grass Florist!</h1>
        <p style="color: #C2D4BE; font-size: 12px;">Your vendor registration has been successfully approved</p>
    </div>

    <div class="content">
        <div class="celebration-box">
            <div>
                <strong style="color: #15803d; font-size: 15px;">Account Approved & Active!</strong>
                <div style="font-size: 12px; color: #166534; margin-top: 2px;">You can now log in to the Seller Portal and start listing books.</div>
            </div>
            <div>
                <span class="status-badge">● Active</span>
            </div>
        </div>

        <p style="font-size: 14px; color: #334155;">
            Dear <strong>{{ $vendor->contact_person ?: $user->name }}</strong>,
        </p>
        <p style="font-size: 14px; color: #475569;">
            We are pleased to inform you that your application for <strong>{{ $vendor->vendor_name }}</strong> has been verified and approved by our onboarding team. Your vendor account is now fully operational.
        </p>

        <div class="section-title">Seller Profile Overview</div>
        <table class="info-grid">
            <tr>
                <td class="label">Store / Brand:</td>
                <td class="value"><strong>{{ $vendor->vendor_name }}</strong></td>
            </tr>
            <tr>
                <td class="label">Login Email:</td>
                <td class="value">{{ $user->email }}</td>
            </tr>
            <tr>
                <td class="label">Support Phone:</td>
                <td class="value">{{ $vendor->vendor_phone }}</td>
            </tr>
            <tr>
                <td class="label">Platform Commission:</td>
                <td class="value">{{ $vendor->commission_rate }}% (+ {{ $vendor->commission_gst_rate }}% GST)</td>
            </tr>
            <tr>
                <td class="label">Account Status:</td>
                <td class="value"><strong style="color: #15803d;">Verified & Approved</strong></td>
            </tr>
        </table>

        <div class="section-title">Next Steps to Start Selling</div>
        <div class="steps-container">
            <ol class="steps-list">
                <li><strong>Access Portal:</strong> Click the button below and log in using your registered email and password.</li>
                <li><strong>Review Profile:</strong> Verify your warehouse pickup address and business details under Store Profile.</li>
                <li><strong>Add Inventory:</strong> Begin listing your available books, assign stock levels, and set competitive prices.</li>
                <li><strong>Process Orders:</strong> When orders arrive, print pick-lists/invoices and prepare shipments for our courier partners.</li>
            </ol>
        </div>

        <div class="btn-container">
            <a href="{{ url('/admin/login') }}" class="btn">Login to Vendor Portal</a>
        </div>

        <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
            If you need any guidance getting started, our merchant support team is ready to assist you at 
            <a href="mailto:info@grassflorist.com" style="color: #8fae2a; font-weight: 600;">info@grassflorist.com</a>.
        </p>

        <p style="font-size: 13px; color: #64748b;">
            Wishing you great success selling on Grass Florist!<br>
            <strong>The Grass Florist Merchant Support Team</strong>
        </p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        <p>
            <a href="{{ config('app.frontend_url', 'https://grassflorist.com') }}/privacy">Privacy Policy</a> | 
            <a href="{{ config('app.frontend_url', 'https://grassflorist.com') }}/terms">Terms of Service</a>
        </p>
    </div>
</div>
</body>
</html>
