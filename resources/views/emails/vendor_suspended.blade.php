<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vendor Account Status Notice - {{ $vendor->vendor_name }}</title>
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
            font-size: 20px;
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
        .alert-card {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            padding: 16px 20px;
            margin-bottom: 22px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-badge {
            display: inline-block;
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
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
        .notice-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 20px 0;
            font-size: 13px;
            color: #475569;
        }
        .notice-box ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }
        .notice-box li {
            margin-bottom: 6px;
        }
        .btn-container {
            text-align: center;
            margin: 28px 0 16px 0;
        }
        .btn {
            display: inline-block;
            background-color: #dc2626;
            color: #ffffff !important;
            padding: 13px 32px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(220, 38, 38, 0.25);
        }
        .btn:hover {
            background-color: #b91c1c;
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
        <h1 style="color: #ffffff; font-size: 20px; margin-top: 10px;">Grass Florist Merchant Notice</h1>
        <p style="color: #C2D4BE; font-size: 12px;">Important information regarding your vendor account status</p>
    </div>

    <div class="content">
        <div class="alert-card">
            <div>
                <strong style="color: #b91c1c; font-size: 15px;">Account Placed On Hold</strong>
                <div style="font-size: 12px; color: #991b1b; margin-top: 2px;">Your vendor operations are currently suspended.</div>
            </div>
            <div>
                <span class="status-badge">● Suspended</span>
            </div>
        </div>

        <p style="font-size: 14px; color: #334155;">
            Dear <strong>{{ $vendor->contact_person ?: $user->name }}</strong>,
        </p>
        <p style="font-size: 14px; color: #475569;">
            This is to inform you that your vendor account associated with <strong>{{ $vendor->vendor_name }}</strong> has been temporarily suspended / placed on hold by our platform administrators.
        </p>

        <div class="section-title">Account Details</div>
        <table class="info-grid">
            <tr>
                <td class="label">Store / Brand:</td>
                <td class="value"><strong>{{ $vendor->vendor_name }}</strong></td>
            </tr>
            <tr>
                <td class="label">Registered Email:</td>
                <td class="value">{{ $user->email }}</td>
            </tr>
            <tr>
                <td class="label">Current Status:</td>
                <td class="value"><strong style="color: #dc2626;">Suspended (On Hold)</strong></td>
            </tr>
            <tr>
                <td class="label">Notice Date:</td>
                <td class="value">{{ now()->format('d M Y, h:i A') }}</td>
            </tr>
        </table>

        <div class="notice-box">
            <strong style="color: #1e293b;">Impact on your seller operations:</strong>
            <ul>
                <li>Access to the vendor administration dashboard is temporarily disabled.</li>
                <li>Your product listings may be temporarily hidden from storefront searches and product pages.</li>
                <li>Any pending or unsettled disbursements will be reviewed alongside your compliance status.</li>
            </ul>
        </div>

        <p style="font-size: 14px; color: #475569;">
            If you believe this action was taken in error, or if you need to submit updated documentation (such as renewed GST, PAN, or bank credentials) to restore your account, please reach out to our compliance desk directly.
        </p>

        <div class="btn-container">
            <a href="mailto:info@grassflorist.com?subject=Vendor%20Account%20Status%20Inquiry%20-%20{{ urlencode($vendor->vendor_name) }}" class="btn">Contact Merchant Compliance Desk</a>
        </div>

        <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
            Email: <a href="mailto:info@grassflorist.com" style="color: #8fae2a; font-weight: 600;">info@grassflorist.com</a><br>
            Please reference your registered store name (<strong>{{ $vendor->vendor_name }}</strong>) in all communications.
        </p>

        <p style="font-size: 13px; color: #64748b;">
            Sincerely,<br>
            <strong>The Grass Florist Merchant Compliance Team</strong>
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
