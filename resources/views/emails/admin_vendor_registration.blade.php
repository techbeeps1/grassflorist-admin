<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Vendor Registration - {{ $vendor->vendor_name }}</title>
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
        .status-card {
            background-color: #fefce8;
            border: 1px solid #fef08a;
            border-left: 4px solid #eab308;
            padding: 14px 18px;
            margin-bottom: 22px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-badge {
            display: inline-block;
            background-color: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
            padding: 4px 12px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            width: 170px;
        }
        .info-grid .value {
            color: #0f172a;
            font-weight: 500;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0 16px 0;
        }
        .btn {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff !important;
            padding: 12px 28px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25);
        }
        .btn:hover {
            background-color: #0369a1;
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
        <h1 style="color: #ffffff; font-size: 20px; margin-top: 10px;">New Vendor Registration Received</h1>
        <p style="color: #C2D4BE; font-size: 12px;">A new seller application has been submitted on Grass Florist</p>
    </div>

    <div class="content">
        <div class="status-card">
            <div>
                <strong style="color: #854d0e; font-size: 14px;">Action Required: Verification Pending</strong>
                <div style="font-size: 12px; color: #a16207; margin-top: 2px;">Review details and activate account in Admin Panel.</div>
            </div>
            <div>
                <span class="status-badge">● Pending</span>
            </div>
        </div>

        <div class="section-title">Store & Contact Details</div>
        <table class="info-grid">
            <tr>
                <td class="label">Store / Business Name:</td>
                <td class="value"><strong>{{ $vendor->vendor_name }}</strong></td>
            </tr>
            <tr>
                <td class="label">Contact Person:</td>
                <td class="value">{{ $vendor->contact_person ?: $user->name }}</td>
            </tr>
            <tr>
                <td class="label">Registered Email:</td>
                <td class="value"><a href="mailto:{{ $user->email }}" style="color: #0284c7;">{{ $user->email }}</a></td>
            </tr>
            <tr>
                <td class="label">Support Phone:</td>
                <td class="value"><a href="tel:{{ $vendor->vendor_phone }}" style="color: #0f172a; text-decoration: none;">{{ $vendor->vendor_phone }}</a></td>
            </tr>
            <tr>
                <td class="label">Website:</td>
                <td class="value">
                    @if($vendor->vendor_website)
                        <a href="{{ $vendor->vendor_website }}" target="_blank" style="color: #0284c7;">{{ $vendor->vendor_website }}</a>
                    @else
                        <span style="color: #94a3b8;">Not provided</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Registration Date:</td>
                <td class="value">{{ $vendor->created_at ? $vendor->created_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A') }}</td>
            </tr>
        </table>

        <div class="section-title">Warehouse & Pickup Location</div>
        <table class="info-grid">
            <tr>
                <td class="label">Address:</td>
                <td class="value">{{ $vendor->vendor_address }}</td>
            </tr>
            <tr>
                <td class="label">City:</td>
                <td class="value">{{ $vendor->city }}</td>
            </tr>
            <tr>
                <td class="label">State:</td>
                <td class="value">{{ $vendor->state }}</td>
            </tr>
            <tr>
                <td class="label">PIN Code:</td>
                <td class="value">{{ $vendor->pincode }}</td>
            </tr>
        </table>

        <div class="section-title">Tax & Legal Identifiers</div>
        <table class="info-grid">
            <tr>
                <td class="label">PAN Number:</td>
                <td class="value"><strong style="letter-spacing: 0.5px;">{{ $vendor->pan_number }}</strong></td>
            </tr>
            <tr>
                <td class="label">GSTIN:</td>
                <td class="value">{{ $vendor->gst_number ?: 'Not provided' }}</td>
            </tr>
            <tr>
                <td class="label">ISBN / Publisher Code:</td>
                <td class="value">{{ $vendor->isbn_number ?: 'Not provided' }}</td>
            </tr>
        </table>

        <div class="section-title">Bank & Settlement Details</div>
        <table class="info-grid">
            <tr>
                <td class="label">Bank Name:</td>
                <td class="value">{{ $vendor->bank_name }}</td>
            </tr>
            <tr>
                <td class="label">Account Holder Name:</td>
                <td class="value">{{ $vendor->account_holder_name }}</td>
            </tr>
            <tr>
                <td class="label">Account Number:</td>
                <td class="value"><strong style="letter-spacing: 1px;">{{ $vendor->account_number }}</strong></td>
            </tr>
            <tr>
                <td class="label">IFSC Code:</td>
                <td class="value"><strong style="letter-spacing: 0.5px;">{{ $vendor->ifsc_code }}</strong></td>
            </tr>
            <tr>
                <td class="label">UPI ID:</td>
                <td class="value">{{ $vendor->upi_id ?: 'Not provided' }}</td>
            </tr>
        </table>

        <div class="btn-container">
            <a href="{{ url('/admin/users') }}" class="btn">View & Approve in Admin Panel</a>
        </div>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        <p>This is an automated administrative notification sent to {{ config('mail.admin_email', env('ADMIN_EMAIL')) }}.</p>
    </div>
</div>
</body>
</html>
