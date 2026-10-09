<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject ?? 'New Contact Inquiry - Grass Florist' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #1E1915;
            background-color: #FAF8F5;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #EFE7DC;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .header {
            background-color: #2D3F33;
            color: #ffffff;
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #ffffff;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 12px;
            color: #C2D4BE;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .content {
            padding: 28px 24px;
        }
        .intro {
            font-size: 15px;
            color: #5A5049;
            margin-bottom: 24px;
        }
        .card {
            background-color: #FAF8F5;
            border: 1px solid #EAE3D7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .field {
            margin-bottom: 12px;
            font-size: 14px;
        }
        .field:last-child {
            margin-bottom: 0;
        }
        .field strong {
            color: #1E1915;
            display: inline-block;
            width: 130px;
        }
        .field a {
            color: #2D3F33;
            font-weight: 600;
            text-decoration: none;
        }
        .message-box {
            background-color: #ffffff;
            border-left: 4px solid #2D3F33;
            padding: 16px;
            border-radius: 4px;
            margin-top: 10px;
            color: #1E1915;
            font-size: 14px;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #8C827A;
            border-top: 1px solid #EFE7DC;
            background-color: #FAF8F5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>GRASS FLORIST</h1>
            <p>Customer Concierge Inquiry</p>
        </div>
        <div class="content">
            <p class="intro">
                You have received a new customer inquiry submitted through the Grass Florist storefront:
            </p>

            <div class="card">
                <div class="field">
                    <strong>Customer Name:</strong>
                    <span>{{ $name ?? 'Guest Visitor' }}</span>
                </div>
                <div class="field">
                    <strong>Email Address:</strong>
                    <span><a href="mailto:{{ $email }}">{{ $email }}</a></span>
                </div>
                @if(!empty($phone))
                <div class="field">
                    <strong>Mobile Number:</strong>
                    <span><a href="tel:{{ $phone }}">{{ $phone }}</a></span>
                </div>
                @endif
                <div class="field">
                    <strong>Subject:</strong>
                    <span>{{ $subject ?? 'General Inquiry' }}</span>
                </div>
                <div class="field">
                    <strong>Received At:</strong>
                    <span>{{ date('d M Y, h:i A') }}</span>
                </div>
            </div>

            <div class="field">
                <strong>Customer Message:</strong>
            </div>
            <div class="message-box">{{ $clientMessage ?? ($emailMessage ?? '') }}</div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Grass Florist Atelier. All rights reserved.</p>
            <p>Jeddah, Kingdom of Saudi Arabia</p>
        </div>
    </div>
</body>
</html>